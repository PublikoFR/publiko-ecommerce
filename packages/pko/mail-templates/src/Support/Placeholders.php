<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

/**
 * Substitution des placeholders `:nom` dans le sujet et l'arbre de contenu
 * (page-builder : sections → colonnes → blocs).
 */
final class Placeholders
{
    /**
     * Champs textuels porteurs de placeholders, par type de bloc. `html`
     * (bloc `text`) est rendu sans échappement côté vue (`{!! !!}`, contenu
     * Tiptap déjà assaini) : les VALEURS substituées y sont donc échappées
     * pour empêcher qu'un nom de client contenant du HTML s'y injecte. Les
     * autres champs sont rendus échappés par Blade (`{{ }}`) : substitution
     * brute, comme avant la bascule vers l'arbre page-builder.
     *
     * @var array<string, array<int, string>>
     */
    private const BLOCK_FIELDS = [
        'text' => ['html'],
        'title' => ['text'],
        'button' => ['label', 'url'],
        'callout' => ['text'],
        'quote' => ['text', 'cite'],
        'list' => ['items'],
        'image' => ['alt'],
    ];

    /** Champs dont le rendu n'échappe pas le HTML : les valeurs substituées le sont. */
    private const ESCAPED_VALUE_FIELDS = ['html'];

    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public static function apply(string $text, array $values, bool $escapeValues = false): string
    {
        if ($values === []) {
            return $text;
        }

        // Les clés les plus longues d'abord : sans ce tri, `:order` remplacerait
        // le début de `:order_reference` et laisserait un `_reference` orphelin.
        $keys = array_keys($values);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $replacements = [];
        foreach ($keys as $key) {
            $value = (string) ($values[$key] ?? '');
            $replacements[':'.$key] = $escapeValues ? e($value) : $value;
        }

        return strtr($text, $replacements);
    }

    /**
     * Applique la substitution à tout le contenu de l'e-mail (arbre page-builder).
     *
     * @param  array{heading: string, sections: array<int, array<string, mixed>>}  $content
     * @param  array<string, string|int|float|null>  $values
     * @return array{heading: string, sections: array<int, array<string, mixed>>}
     */
    public static function applyToContent(array $content, array $values): array
    {
        $content['sections'] = array_map(
            static fn (array $section): array => self::applyToSection($section, $values),
            $content['sections'] ?? [],
        );

        return $content;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, string|int|float|null>  $values
     * @return array<string, mixed>
     */
    private static function applyToSection(array $section, array $values): array
    {
        $section['columns'] = array_map(
            static fn (array $column): array => [
                'blocks' => array_map(
                    static fn (array $block): array => self::applyToBlock($block, $values),
                    $column['blocks'] ?? [],
                ),
            ],
            $section['columns'] ?? [],
        );

        return $section;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, string|int|float|null>  $values
     * @return array<string, mixed>
     */
    private static function applyToBlock(array $block, array $values): array
    {
        $fields = self::BLOCK_FIELDS[$block['type'] ?? ''] ?? [];

        foreach ($fields as $field) {
            $escape = in_array($field, self::ESCAPED_VALUE_FIELDS, true);

            if ($field === 'items' && is_array($block['items'] ?? null)) {
                $block['items'] = array_map(
                    static fn (mixed $item): mixed => is_string($item) ? self::apply($item, $values, $escape) : $item,
                    $block['items'],
                );

                continue;
            }

            if (isset($block[$field]) && is_string($block[$field])) {
                $block[$field] = self::apply($block[$field], $values, $escape);
            }
        }

        return $block;
    }

    /**
     * Placeholders `:nom` encore présents dans le contenu après substitution.
     * Sert au contrôle de cohérence de l'éditeur back-office.
     *
     * @param  array{heading?: string, sections?: array<int, array<string, mixed>>}  $content
     * @return array<int, string>
     */
    public static function found(string $subject, array $content): array
    {
        $haystack = $subject;

        foreach ($content['sections'] ?? [] as $section) {
            foreach ($section['columns'] ?? [] as $column) {
                foreach ($column['blocks'] ?? [] as $block) {
                    $haystack .= ' '.self::blockHaystack($block);
                }
            }
        }

        preg_match_all('/:([a-z][a-z0-9_]*)/i', $haystack, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /** @param array<string, mixed> $block */
    private static function blockHaystack(array $block): string
    {
        $fields = self::BLOCK_FIELDS[$block['type'] ?? ''] ?? [];
        $parts = [];

        foreach ($fields as $field) {
            if ($field === 'items' && is_array($block['items'] ?? null)) {
                $parts = [...$parts, ...array_filter($block['items'], 'is_string')];

                continue;
            }

            if (isset($block[$field]) && is_string($block[$field])) {
                $parts[] = $block[$field];
            }
        }

        return implode(' ', $parts);
    }
}

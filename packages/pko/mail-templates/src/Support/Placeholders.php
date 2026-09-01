<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

/**
 * Substitution des placeholders `:nom` dans le sujet et les blocs.
 */
final class Placeholders
{
    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public static function apply(string $text, array $values): string
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
            $replacements[':'.$key] = (string) ($values[$key] ?? '');
        }

        return strtr($text, $replacements);
    }

    /**
     * Applique la substitution à tous les champs textuels d'une liste de blocs.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, string|int|float|null>  $values
     * @return array<int, array<string, mixed>>
     */
    public static function applyToBlocks(array $blocks, array $values): array
    {
        return array_map(static function (array $block) use ($values): array {
            foreach (['text', 'label', 'url'] as $field) {
                if (isset($block[$field]) && is_string($block[$field])) {
                    $block[$field] = self::apply($block[$field], $values);
                }
            }

            return $block;
        }, $blocks);
    }

    /**
     * Placeholders `:nom` encore présents dans le contenu après substitution.
     * Sert au contrôle de cohérence de l'éditeur back-office.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, string>
     */
    public static function found(string $subject, array $blocks): array
    {
        $haystack = $subject;

        foreach ($blocks as $block) {
            foreach (['text', 'label', 'url'] as $field) {
                if (isset($block[$field]) && is_string($block[$field])) {
                    $haystack .= ' '.$block[$field];
                }
            }
        }

        preg_match_all('/:([a-z][a-z0-9_]*)/i', $haystack, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}

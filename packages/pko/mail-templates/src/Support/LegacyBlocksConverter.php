<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

use Pko\PageBuilder\Services\PageBuilderManager;

/**
 * Convertit l'ancien format `blocks` (liste plate de blocs typés
 * `paragraph|heading|button|divider|signature`) vers l'arborescence
 * page-builder (`{heading, sections:[{layout, columns:[{blocks:[]}]}]}`).
 *
 * Utilisé une seule fois par la migration qui bascule `pko_mail_templates`
 * vers la colonne `content`. `signature` n'a pas d'équivalent dans le schéma
 * page-builder (`additionalProperties: false`) : converti en bloc `text`,
 * comme `paragraph` et `heading`.
 */
final class LegacyBlocksConverter
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{heading: string, sections: array<int, array<string, mixed>>}
     */
    public static function convert(array $blocks): array
    {
        $converted = [];

        foreach ($blocks as $block) {
            $mapped = self::convertBlock($block);

            if ($mapped !== null) {
                $converted[] = $mapped;
            }
        }

        return PageBuilderManager::normalize([
            'heading' => '',
            'sections' => [
                [
                    'layout' => PageBuilderManager::LAYOUT_1COL,
                    'columns' => [
                        ['blocks' => $converted],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private static function convertBlock(array $block): ?array
    {
        $type = $block['type'] ?? 'paragraph';

        return match ($type) {
            'paragraph', 'heading', 'signature' => [
                'type' => PageBuilderManager::BLOCK_TEXT,
                // Le rendu legacy échappait le texte puis convertissait les
                // retours à la ligne en <br> (nl2br(e($text))). On reproduit
                // ce comportement pour figer le rendu visuel à l'identique.
                'html' => nl2br(e((string) ($block['text'] ?? ''))),
            ],
            'button' => [
                'type' => PageBuilderManager::BLOCK_BUTTON,
                'label' => (string) ($block['label'] ?? ''),
                'url' => (string) ($block['url'] ?? ''),
                'variant' => in_array($block['variant'] ?? null, ['primary', 'accent'], true)
                    ? $block['variant']
                    : 'primary',
            ],
            'divider' => [
                'type' => PageBuilderManager::BLOCK_SEPARATOR,
                'variant' => 'line',
            ],
            default => null,
        };
    }
}

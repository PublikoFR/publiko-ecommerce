<?php

declare(strict_types=1);

namespace Pko\PageBuilder\Services;

use Illuminate\Support\Str;

/**
 * Validates and normalises a page-builder content tree. The canonical JSON
 * schema lives at resources/schema/content.schema.json. This manager does a
 * pragmatic structural validation (enough for our own UI + AI-generated
 * payloads) — we don't pull a full JSON-Schema validator dependency.
 *
 * Shape (minimal) :
 *
 *   {
 *     "sections": [
 *       {
 *         "id": "sec_*",
 *         "layout": "1col|2col|3col",
 *         "padding": {"t":0,"r":0,"b":0,"l":0},
 *         "margin":  {"t":0,"b":0},
 *         "background_color": "#rrggbb" | null,
 *         "text_color": "#rrggbb" | null,
 *         "columns": [ { "blocks": [ { "id":"...", "type":"text|image|code", ... } ] } ]
 *       }
 *     ]
 *   }
 */
final class PageBuilderManager
{
    public const LAYOUT_1COL = '1col';

    public const LAYOUT_2COL = '2col';

    public const LAYOUT_3COL = '3col';

    public const BLOCK_TEXT = 'text';

    public const BLOCK_IMAGE = 'image';

    public const BLOCK_CODE = 'code';

    /** @return array<int, string> */
    public static function allowedLayouts(): array
    {
        return [self::LAYOUT_1COL, self::LAYOUT_2COL, self::LAYOUT_3COL];
    }

    public static function columnsForLayout(string $layout): int
    {
        return match ($layout) {
            self::LAYOUT_2COL => 2,
            self::LAYOUT_3COL => 3,
            default => 1,
        };
    }

    /**
     * Normalise any payload into the canonical shape. Missing keys default to
     * sensible values, unknown keys are dropped. Never throws.
     *
     * @param  array<mixed>|null  $content
     * @return array{sections: array<int, array<string, mixed>>}
     */
    public static function normalize(?array $content): array
    {
        $sections = [];
        foreach (($content['sections'] ?? []) as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $sections[] = self::normalizeSection($raw);
        }

        return ['sections' => $sections];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function normalizeSection(array $raw): array
    {
        $layout = in_array($raw['layout'] ?? null, self::allowedLayouts(), true)
            ? $raw['layout']
            : self::LAYOUT_1COL;

        $columnsCount = self::columnsForLayout($layout);
        $columnsIn = is_array($raw['columns'] ?? null) ? $raw['columns'] : [];
        $columnsOut = [];
        for ($i = 0; $i < $columnsCount; $i++) {
            $columnsOut[] = self::normalizeColumn($columnsIn[$i] ?? []);
        }

        return [
            'id' => self::ensureId($raw['id'] ?? null, 'sec_'),
            'layout' => $layout,
            'padding' => self::normalizeBox($raw['padding'] ?? [], ['t', 'r', 'b', 'l']),
            'margin' => self::normalizeBox($raw['margin'] ?? [], ['t', 'b']),
            'background_color' => self::normalizeColor($raw['background_color'] ?? null),
            'text_color' => self::normalizeColor($raw['text_color'] ?? null),
            'columns' => $columnsOut,
        ];
    }

    /**
     * @param  array<mixed>  $raw
     * @return array{blocks: array<int, array<string, mixed>>}
     */
    private static function normalizeColumn(mixed $raw): array
    {
        $blocks = [];
        if (is_array($raw) && is_array($raw['blocks'] ?? null)) {
            foreach ($raw['blocks'] as $blockRaw) {
                if (! is_array($blockRaw)) {
                    continue;
                }
                $normalized = self::normalizeBlock($blockRaw);
                if ($normalized !== null) {
                    $blocks[] = $normalized;
                }
            }
        }

        return ['blocks' => $blocks];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private static function normalizeBlock(array $raw): ?array
    {
        $type = $raw['type'] ?? null;

        return match ($type) {
            self::BLOCK_TEXT => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_TEXT,
                'html' => is_string($raw['html'] ?? null) ? $raw['html'] : '',
            ],
            self::BLOCK_IMAGE => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_IMAGE,
                'media_id' => isset($raw['media_id']) ? (int) $raw['media_id'] : null,
                'url' => isset($raw['url']) && is_string($raw['url']) ? $raw['url'] : null,
                'alt' => isset($raw['alt']) && is_string($raw['alt']) ? $raw['alt'] : '',
            ],
            self::BLOCK_CODE => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_CODE,
                'language' => self::normalizeLanguage($raw['language'] ?? null),
                'content' => is_string($raw['content'] ?? null) ? $raw['content'] : '',
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<int, string>  $keys
     * @return array<string, int>
     */
    private static function normalizeBox(array|string|null $raw, array $keys): array
    {
        $arr = is_array($raw) ? $raw : [];
        $out = [];
        foreach ($keys as $k) {
            $v = (int) ($arr[$k] ?? 0);
            $out[$k] = max(0, min(400, $v));
        }

        return $out;
    }

    private static function normalizeColor(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : null;
    }

    private static function normalizeLanguage(mixed $value): string
    {
        $allowed = ['plain', 'php', 'js', 'ts', 'html', 'css', 'bash', 'json', 'sql', 'yaml'];
        $value = is_string($value) ? $value : 'plain';

        return in_array($value, $allowed, true) ? $value : 'plain';
    }

    private static function ensureId(mixed $value, string $prefix): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $prefix.Str::random(8);
    }

    /**
     * Fabriquer une section vide prête à être insérée dans le state.
     *
     * @return array<string, mixed>
     */
    public static function newSection(string $layout = self::LAYOUT_1COL): array
    {
        return self::normalizeSection(['layout' => $layout]);
    }

    /**
     * Fabriquer un bloc vide du type demandé.
     *
     * @return array<string, mixed>|null
     */
    public static function newBlock(string $type): ?array
    {
        return self::normalizeBlock(['type' => $type]);
    }
}

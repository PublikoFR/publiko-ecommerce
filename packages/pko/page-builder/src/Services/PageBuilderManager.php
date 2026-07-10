<?php

declare(strict_types=1);

namespace Pko\PageBuilder\Services;

use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Validates and normalises a page-builder content tree. The canonical JSON
 * schema lives at resources/schema/content.schema.json. This manager does a
 * pragmatic structural validation (enough for our own UI + AI-generated
 * payloads) — we don't pull a full JSON-Schema validator dependency.
 *
 * Shape (minimal) :
 *
 *   {
 *     "heading": "Titre H1 on-page (peut différer du nom en base, SEO)",
 *     "sections": [
 *       {
 *         "id": "sec_*",
 *         "layout": "1col|2col|3col",
 *         "padding": {"t":0,"r":0,"b":0,"l":0},
 *         "margin":  {"t":0,"b":0},
 *         "background_color": "#rrggbb" | null,
 *         "text_color": "#rrggbb" | null,
 *         "columns": [ { "blocks": [ { "id":"...", "type":"text|image|code|quote|button|separator", ... } ] } ]
 *       }
 *     ]
 *   }
 */
final class PageBuilderManager
{
    public const LAYOUT_1COL = '1col';

    public const LAYOUT_2COL = '2col';

    public const LAYOUT_3COL = '3col';

    public const LAYOUT_4COL = '4col';

    public const LAYOUT_5COL = '5col';

    public const LAYOUT_6COL = '6col';

    public const BLOCK_TEXT = 'text';

    public const BLOCK_IMAGE = 'image';

    public const BLOCK_CODE = 'code';

    public const BLOCK_QUOTE = 'quote';

    public const BLOCK_BUTTON = 'button';

    public const BLOCK_SEPARATOR = 'separator';

    public const BLOCK_CALLOUT = 'callout';

    public const BLOCK_TITLE = 'title';

    public const BLOCK_VIDEO = 'video';

    public const BLOCK_LIST = 'list';

    public const BLOCK_ACCORDION = 'accordion';

    public const BLOCK_GALLERY = 'gallery';

    /** Variantes visuelles autorisées pour le bloc bouton. */
    public const BUTTON_VARIANTS = ['primary', 'accent', 'secondary'];

    /** Variantes autorisées pour le bloc séparateur (line + espaces réglables). */
    public const SEPARATOR_VARIANTS = ['line', 'space', 'space-sm', 'space-md', 'space-lg', 'space-xl'];

    /** Variantes autorisées pour le bloc encart (callout). */
    public const CALLOUT_VARIANTS = ['info', 'warning', 'danger'];

    /** Niveaux autorisés pour le bloc titre. */
    public const TITLE_LEVELS = ['h2', 'h3'];

    /** Styles autorisés pour le bloc liste. */
    public const LIST_STYLES = ['bullet', 'check'];

    /** Nombre de colonnes autorisées pour la galerie. */
    public const GALLERY_COLUMNS = [2, 3, 4];

    /** Langages autorisés pour le bloc code. */
    public const CODE_LANGUAGES = ['plain', 'php', 'js', 'ts', 'html', 'css', 'bash', 'json', 'sql', 'yaml'];

    /** @return array<int, string> */
    public static function allowedLayouts(): array
    {
        return [
            self::LAYOUT_1COL, self::LAYOUT_2COL, self::LAYOUT_3COL,
            self::LAYOUT_4COL, self::LAYOUT_5COL, self::LAYOUT_6COL,
        ];
    }

    public static function columnsForLayout(string $layout): int
    {
        return match ($layout) {
            self::LAYOUT_2COL => 2,
            self::LAYOUT_3COL => 3,
            self::LAYOUT_4COL => 4,
            self::LAYOUT_5COL => 5,
            self::LAYOUT_6COL => 6,
            default => 1,
        };
    }

    /**
     * Normalise any payload into the canonical shape. Missing keys default to
     * sensible values, unknown keys are dropped. Never throws.
     *
     * @param  array<mixed>|null  $content
     * @return array{heading: string, sections: array<int, array<string, mixed>>}
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

        return [
            'heading' => self::normalizeHeading($content['heading'] ?? null),
            'sections' => $sections,
        ];
    }

    /**
     * Titre H1 « on-page » : texte brut (les tags sont retirés), trimmé et borné.
     * Il peut différer du nom du contenu en base (SEO) et n'est jamais supprimable
     * côté éditeur — c'est un champ dédié, pas un bloc du canvas.
     */
    public static function normalizeHeading(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return Str::limit(trim(strip_tags($value)), 250, '');
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
                'html' => self::sanitizeHtml(is_string($raw['html'] ?? null) ? $raw['html'] : ''),
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
            self::BLOCK_QUOTE => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_QUOTE,
                'text' => is_string($raw['text'] ?? null) ? trim(strip_tags($raw['text'])) : '',
                'cite' => is_string($raw['cite'] ?? null) ? trim(strip_tags($raw['cite'])) : '',
            ],
            self::BLOCK_BUTTON => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_BUTTON,
                'label' => is_string($raw['label'] ?? null) ? Str::limit(trim(strip_tags($raw['label'])), 80, '') : '',
                'url' => self::normalizeUrl($raw['url'] ?? null),
                'variant' => in_array($raw['variant'] ?? null, self::BUTTON_VARIANTS, true) ? $raw['variant'] : 'primary',
            ],
            self::BLOCK_SEPARATOR => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_SEPARATOR,
                'variant' => in_array($raw['variant'] ?? null, self::SEPARATOR_VARIANTS, true) ? $raw['variant'] : 'line',
            ],
            self::BLOCK_CALLOUT => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_CALLOUT,
                'variant' => in_array($raw['variant'] ?? null, self::CALLOUT_VARIANTS, true) ? $raw['variant'] : 'info',
                'text' => is_string($raw['text'] ?? null) ? trim(strip_tags($raw['text'])) : '',
            ],
            self::BLOCK_TITLE => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_TITLE,
                'level' => in_array($raw['level'] ?? null, self::TITLE_LEVELS, true) ? $raw['level'] : 'h2',
                'text' => is_string($raw['text'] ?? null) ? Str::limit(trim(strip_tags($raw['text'])), 200, '') : '',
            ],
            self::BLOCK_VIDEO => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_VIDEO,
                // Résolue au rendu par VideoUrlResolver ; on ne garde qu'une URL http(s).
                'url' => (is_string($raw['url'] ?? null) && preg_match('#^https?://#i', trim($raw['url'])) === 1) ? trim($raw['url']) : '',
            ],
            self::BLOCK_LIST => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_LIST,
                'style' => in_array($raw['style'] ?? null, self::LIST_STYLES, true) ? $raw['style'] : 'bullet',
                'items' => self::normalizeStringList($raw['items'] ?? null, 50, 300),
            ],
            self::BLOCK_ACCORDION => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_ACCORDION,
                'items' => self::normalizeAccordionItems($raw['items'] ?? null),
            ],
            self::BLOCK_GALLERY => [
                'id' => self::ensureId($raw['id'] ?? null, 'blk_'),
                'type' => self::BLOCK_GALLERY,
                'media_ids' => self::normalizeIntList($raw['media_ids'] ?? null, 40),
                'columns' => in_array((int) ($raw['columns'] ?? 3), self::GALLERY_COLUMNS, true) ? (int) ($raw['columns'] ?? 3) : 3,
            ],
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeStringList(mixed $raw, int $max, int $itemMaxLen): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (! is_string($v)) {
                continue;
            }
            $v = Str::limit(trim(strip_tags($v)), $itemMaxLen, '');
            if ($v !== '') {
                $out[] = $v;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{q: string, a: string}>
     */
    private static function normalizeAccordionItems(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $it) {
            if (! is_array($it)) {
                continue;
            }
            $q = is_string($it['q'] ?? null) ? Str::limit(trim(strip_tags($it['q'])), 200, '') : '';
            $a = is_string($it['a'] ?? null) ? trim(strip_tags($it['a'])) : '';
            if ($q === '' && $a === '') {
                continue;
            }
            $out[] = ['q' => $q, 'a' => $a];
            if (count($out) >= 30) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeIntList(mixed $raw, int $max): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id > 0 && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * N'autorise que des URLs sûres pour un href de bouton : http(s), lien
     * interne (/…), ancre (#…), mailto: et tel:. Tout le reste (javascript:,
     * data:, etc.) est neutralisé en chaîne vide.
     */
    private static function normalizeUrl(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $value) === 1 ? $value : '';
    }

    /**
     * Sanitize HTML user-content via HTMLPurifier avec le profil 'pko-content'
     * (config/purifier.php). Élimine <script>, on* handlers, javascript:, data:
     * URIs et tout HTML non listé dans HTML.Allowed. Appliqué au save ET au
     * render pour defense-in-depth contre le stored XSS.
     */
    public static function sanitizeHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        return Purifier::clean($html, 'pko-content');
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
        $value = is_string($value) ? $value : 'plain';

        return in_array($value, self::CODE_LANGUAGES, true) ? $value : 'plain';
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
        // Les tuiles callout de la palette encodent la variante dans le type
        // (callout-info / callout-warning / callout-danger) pour insérer un
        // encart pré-configuré sans param supplémentaire côté drag&drop.
        if (str_starts_with($type, 'callout-')) {
            return self::normalizeBlock([
                'type' => self::BLOCK_CALLOUT,
                'variant' => substr($type, strlen('callout-')),
            ]);
        }

        return self::normalizeBlock(['type' => $type]);
    }

    /**
     * Catalogue auto-décrit des blocs disponibles, destiné à une IA qui compose
     * une page. Les valeurs autorisées (variantes, layouts, langages…) sont
     * tirées des constantes → jamais désynchronisé du normaliseur.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function blockCatalog(): array
    {
        return [
            [
                'type' => self::BLOCK_TEXT,
                'label' => 'Texte',
                'description' => 'Paragraphe(s) en HTML riche. Le HTML est assaini (balises de mise en forme et liens autorisés, scripts retirés).',
                'fields' => ['html' => 'string (HTML)'],
                'example' => ['type' => 'text', 'html' => '<p>Un paragraphe avec un <a href="/contact">lien</a>.</p>'],
            ],
            [
                'type' => self::BLOCK_TITLE,
                'label' => 'Titre',
                'description' => 'Titre de section (H2 ou H3). Le H1 de la page est le champ `heading` du contenu, pas un bloc.',
                'fields' => ['level' => self::TITLE_LEVELS, 'text' => 'string (≤200)'],
                'example' => ['type' => 'title', 'level' => 'h2', 'text' => 'Nos services'],
            ],
            [
                'type' => self::BLOCK_IMAGE,
                'label' => 'Image',
                'description' => 'Image unique. Fournir `media_id` (id média existant) ou `url` (URL absolue).',
                'fields' => ['media_id' => 'int|null', 'url' => 'string|null', 'alt' => 'string'],
                'example' => ['type' => 'image', 'media_id' => null, 'url' => 'https://exemple.test/photo.jpg', 'alt' => 'Description'],
            ],
            [
                'type' => self::BLOCK_GALLERY,
                'label' => 'Galerie',
                'description' => 'Grille d’images (lightbox au clic côté front). `media_ids` = ids médias existants.',
                'fields' => ['media_ids' => 'int[]', 'columns' => self::GALLERY_COLUMNS],
                'example' => ['type' => 'gallery', 'media_ids' => [12, 13, 14], 'columns' => 3],
            ],
            [
                'type' => self::BLOCK_VIDEO,
                'label' => 'Vidéo',
                'description' => 'Vidéo intégrée. `url` = lien YouTube/Vimeo/Dailymotion ou fichier .mp4 (http(s) uniquement).',
                'fields' => ['url' => 'string (http(s))'],
                'example' => ['type' => 'video', 'url' => 'https://youtu.be/xxxxxxxxxxx'],
            ],
            [
                'type' => self::BLOCK_LIST,
                'label' => 'Liste',
                'description' => 'Liste à puces ou à coches. `items` = tableau de chaînes.',
                'fields' => ['style' => self::LIST_STYLES, 'items' => 'string[]'],
                'example' => ['type' => 'list', 'style' => 'check', 'items' => ['Livraison 48h', 'Garantie 2 ans']],
            ],
            [
                'type' => self::BLOCK_QUOTE,
                'label' => 'Citation',
                'description' => 'Citation avec auteur/source optionnel.',
                'fields' => ['text' => 'string', 'cite' => 'string'],
                'example' => ['type' => 'quote', 'text' => 'La qualité avant tout.', 'cite' => 'Notre fondateur'],
            ],
            [
                'type' => self::BLOCK_BUTTON,
                'label' => 'Bouton',
                'description' => 'Bouton d’action. `url` restreinte à http(s), lien interne /…, ancre #…, mailto:, tel:.',
                'fields' => ['label' => 'string (≤80)', 'url' => 'string', 'variant' => self::BUTTON_VARIANTS],
                'example' => ['type' => 'button', 'label' => 'Nous contacter', 'url' => '/contact', 'variant' => 'primary'],
            ],
            [
                'type' => self::BLOCK_CALLOUT,
                'label' => 'Encart',
                'description' => 'Encart coloré avec picto : info (bleu), warning (orange), danger (rouge).',
                'fields' => ['variant' => self::CALLOUT_VARIANTS, 'text' => 'string'],
                'example' => ['type' => 'callout', 'variant' => 'info', 'text' => 'Livraison offerte dès 100€.'],
            ],
            [
                'type' => self::BLOCK_ACCORDION,
                'label' => 'Accordéon / FAQ',
                'description' => 'Questions/réponses repliables. Génère automatiquement un JSON-LD FAQPage (SEO).',
                'fields' => ['items' => 'array<{q: string, a: string}>'],
                'example' => ['type' => 'accordion', 'items' => [['q' => 'Délai de livraison ?', 'a' => '48h ouvrées.']]],
            ],
            [
                'type' => self::BLOCK_CODE,
                'label' => 'Code',
                'description' => 'Bloc de code monospace.',
                'fields' => ['language' => self::CODE_LANGUAGES, 'content' => 'string'],
                'example' => ['type' => 'code', 'language' => 'bash', 'content' => 'php artisan migrate'],
            ],
            [
                'type' => self::BLOCK_SEPARATOR,
                'label' => 'Séparateur',
                'description' => 'Ligne de séparation ou espace vertical réglable.',
                'fields' => ['variant' => self::SEPARATOR_VARIANTS],
                'example' => ['type' => 'separator', 'variant' => 'space-md'],
            ],
        ];
    }

    /**
     * Exemple complet d’un arbre `content` valide, à montrer à l’IA.
     *
     * @return array{heading: string, sections: array<int, array<string, mixed>>}
     */
    public static function exampleContent(): array
    {
        return self::normalize([
            'heading' => 'Bienvenue chez nous',
            'sections' => [
                ['layout' => '1col', 'columns' => [['blocks' => [
                    ['type' => 'title', 'level' => 'h2', 'text' => 'Notre mission'],
                    ['type' => 'text', 'html' => '<p>Nous accompagnons les professionnels au quotidien.</p>'],
                    ['type' => 'callout', 'variant' => 'info', 'text' => 'Livraison offerte dès 100€ d’achat.'],
                ]]]],
                ['layout' => '2col', 'columns' => [
                    ['blocks' => [['type' => 'list', 'style' => 'check', 'items' => ['Devis en 24h', 'Support dédié', 'Garantie 2 ans']]]],
                    ['blocks' => [['type' => 'button', 'label' => 'Demander un devis', 'url' => '/contact', 'variant' => 'accent']]],
                ]],
                ['layout' => '1col', 'columns' => [['blocks' => [
                    ['type' => 'accordion', 'items' => [
                        ['q' => 'Quels sont les délais ?', 'a' => 'Expédition sous 48h ouvrées.'],
                        ['q' => 'Puis-je retourner un article ?', 'a' => 'Oui, sous 14 jours.'],
                    ]],
                ]]]],
            ],
        ]);
    }
}

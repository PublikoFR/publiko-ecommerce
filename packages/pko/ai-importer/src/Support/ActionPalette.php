<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use Pko\AiImporter\Actions\ActionRegistry;

/**
 * Categorised palette of pipeline action types, mirroring the PrestaShop
 * « Pipeline d'actions » modal. Single source of truth for the grouped Select
 * shown in the editor and for the human labels rendered in the row summary.
 *
 * Categories & labels are French (UI), action type keys match the
 * {@see ActionRegistry} identifiers exactly.
 */
final class ActionPalette
{
    /**
     * Ordered category => (type => label). Mirrors the PS palette sections.
     *
     * @var array<string, array<string, string>>
     */
    private const PALETTE = [
        'Logique' => [
            'condition' => 'Condition (SI / ALORS / SINON)',
            'conditional' => 'Condition simple (ternaire)',
        ],
        'Calcul' => [
            'math' => 'Calcul (× ÷ + −)',
            'round' => 'Arrondir',
        ],
        'Texte' => [
            'change_case' => 'Casse (Maj / Min / Capitale)',
            'trim' => 'Nettoyer les espaces',
            'slugify' => 'Slug',
            'truncate' => 'Tronquer',
        ],
        'Remplacement' => [
            'replace' => 'Remplacer un texte',
            'regex_replace' => 'Remplacer (expression régulière)',
        ],
        'Combiner' => [
            'concat' => 'Concaténer',
            'template' => 'Template',
            'prefix' => 'Préfixe',
            'suffix' => 'Suffixe',
            'copy' => 'Copier une colonne',
        ],
        'Correspondance' => [
            'map' => 'Table de correspondance',
            'category_map' => 'Mapping catégories (table → fil d\'Ariane)',
            'parse_category_breadcrumb' => 'Mapping catégories (fil d\'Ariane)',
        ],
        'Dates & validation' => [
            'date_format' => 'Format de date',
            'validate_ean13' => 'Valider EAN-13',
        ],
        'IA' => [
            'llm_transform' => 'Prompt IA',
        ],
        'Agrégation' => [
            'multiline_aggregate' => 'Multi-lignes (1-N)',
            'feature_build' => 'Construire des caractéristiques',
            'parse_features_string' => 'Parser des caractéristiques (texte)',
        ],
    ];

    /**
     * Grouped options ready for a Filament Select.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(bool $includeCondition = true): array
    {
        $palette = self::PALETTE;

        if (! $includeCondition) {
            unset($palette['Logique']);
        }

        // Surface any type registered at runtime but absent from the curated
        // palette so it stays selectable (no silent loss of an action type).
        $known = [];
        foreach ($palette as $types) {
            $known += $types;
        }
        $extra = [];
        foreach (array_keys(ActionRegistry::all()) as $type) {
            if ($type === 'condition' && ! $includeCondition) {
                continue;
            }
            if (! isset($known[$type]) && ! self::isLegacyAlias($type)) {
                $extra[$type] = $type;
            }
        }
        if ($extra !== []) {
            $palette['Autres'] = $extra;
        }

        return $palette;
    }

    /**
     * Flat type => French label map, including a fallback for unknown types.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $flat = [];
        foreach (self::PALETTE as $types) {
            foreach ($types as $type => $label) {
                $flat[$type] = $label;
            }
        }

        return $flat;
    }

    public static function label(string $type): string
    {
        return self::labels()[$type] ?? $type;
    }

    private static function isLegacyAlias(string $type): bool
    {
        return in_array($type, [
            'multiply', 'divide', 'add', 'subtract',
            'uppercase', 'lowercase', 'capitalize',
        ], true);
    }
}

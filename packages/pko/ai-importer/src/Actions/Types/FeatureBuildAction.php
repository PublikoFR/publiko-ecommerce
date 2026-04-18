<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Illuminate\Support\Str;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

/**
 * Builds the `features` hash expected by `LunarProductWriter` from one or more
 * source columns. Routes into the notre package `catalog-features` tables (NOT Lunar
 * native attribute_data).
 *
 * Config shape:
 *
 * ```json
 * {
 *   "type": "feature_build",
 *   "families": {
 *     "marque":       { "col": "MARQUE" },
 *     "couleur":      { "col": "COLOR", "multi_value": true, "separator": "," },
 *     "applications": { "col": "USAGE", "multi_value": true, "separator": "|", "slugify": true },
 *     "matiere":      { "col": "MATERIAL", "values_map": { "alu": "aluminium", "pvc": "pvc" } }
 *   }
 * }
 * ```
 *
 * Output: `{marque: ["somfy"], couleur: ["rouge","bleu"], applications: ["residentiel"]}`
 *
 * Each declared family reads one source column from the primary row. Values
 * are slugified (kebab-case) by default so they match the `handle` column
 * of `pko_feature_values`. Set `slugify: false` per family if the source
 * column already contains handles.
 *
 * `values_map` lets config authors hardcode a translation table from source
 * values to canonical handles (useful when the fournisseur uses codes like
 * `alu`/`pvc` that need explicit mapping).
 */
final class FeatureBuildAction extends Action
{
    /**
     * @param  array<string, array<string, mixed>>  $families
     */
    public function __construct(public readonly array $families = []) {}

    public static function type(): string
    {
        return 'feature_build';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $out = [];

        foreach ($this->families as $familyHandle => $cfg) {
            $col = (string) ($cfg['col'] ?? '');
            if ($col === '') {
                continue;
            }
            $raw = $ctx->row[$col] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }

            $tokens = (bool) ($cfg['multi_value'] ?? false)
                ? array_map('trim', explode((string) ($cfg['separator'] ?? ','), (string) $raw))
                : [(string) $raw];

            $map = is_array($cfg['values_map'] ?? null) ? $cfg['values_map'] : [];
            $slugify = (bool) ($cfg['slugify'] ?? true);

            $handles = [];
            foreach ($tokens as $t) {
                if ($t === '') {
                    continue;
                }
                $canonical = $map[$t] ?? $t;
                $handles[] = $slugify ? Str::slug($canonical) : (string) $canonical;
            }

            if ($handles !== []) {
                $out[(string) $familyHandle] = array_values(array_unique($handles));
            }
        }

        return $out;
    }
}

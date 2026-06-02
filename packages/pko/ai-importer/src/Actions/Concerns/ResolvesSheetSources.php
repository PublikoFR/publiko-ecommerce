<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Concerns;

use Pko\AiImporter\Actions\ExecutionContext;

/**
 * Resolves a "source" reference to a string value, accepting two shapes
 * (matches the PrestaShop Publiko AI Importer config — cf.
 * `config/example-avec-actions.json` of the read-only PS module):
 *
 *  - A plain string  → a column key read from the primary row (`ctx->row`).
 *    Backward-compatible legacy shape.
 *  - An object `{"col": "URL_IMAGE_1", "sheet": "B03_MEDIA"}` → a column read
 *    from a secondary sheet's first joined row (`ctx->sheets[sheet][0][col]`).
 *
 * Sheet resolution mirrors `ParseFileToStagingJob` and `ConditionAction`:
 *   - `sheet` empty / null / not present in `ctx->sheets` (i.e. the primary
 *     sheet, or a secondary sheet with no joined row for this line) → read
 *     `ctx->row[col]`.
 *   - `sheet` present in `ctx->sheets` (a joined secondary sheet) → read the
 *     first joined row `ctx->sheets[sheet][0][col]`.
 *
 * For a 1-N relation (`relation: many`) only the first joined row is read; use
 * the `multiline_aggregate` action when every joined row matters.
 */
trait ResolvesSheetSources
{
    /**
     * @param  string|array<string, mixed>  $source  column key, or `{col, sheet}` object
     */
    protected function resolveSource(string|array $source, ExecutionContext $ctx): string
    {
        if (is_string($source)) {
            return (string) ($ctx->row[$source] ?? '');
        }

        $col = $source['col'] ?? null;
        if (! is_string($col) || $col === '') {
            return '';
        }

        $sheet = $source['sheet'] ?? null;
        $sheet = is_string($sheet) ? $sheet : '';

        // Empty sheet or a sheet absent from the joined secondary sheets
        // (primary sheet, or no joined row) → fall back to the primary row.
        if ($sheet === '' || ! isset($ctx->sheets[$sheet])) {
            return (string) ($ctx->row[$col] ?? '');
        }

        $rows = $ctx->sheets[$sheet];
        $firstRow = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];

        return (string) ($firstRow[$col] ?? '');
    }
}

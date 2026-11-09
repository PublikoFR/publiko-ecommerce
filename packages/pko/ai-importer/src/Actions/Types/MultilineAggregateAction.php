<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Support\NumberParser;

/**
 * Aggregate secondary-sheet rows (1-N join) into a single string/array/count.
 *
 * The secondary sheet must be pre-indexed by `join_key` in `ctx->sheets`.
 * Each entry is a row array; `filter_type` restricts to rows where the
 * `type_col` column matches (e.g. only "CODE_IMAGE" rows). `type_col` names
 * the column holding the type (defaults to `type`); PS sheet configs declare
 * it explicitly (e.g. `"type_col": "MTYP"`).
 */
final class MultilineAggregateAction extends Action
{
    /**
     * @param  array<string, array<string, mixed>>|array<int, string>  $columns
     */
    public function __construct(
        public readonly string $sheet = '',
        public readonly string $method = 'concat', // first|last|count|sum|concat|template|json_array
        public readonly string $separator = '|',
        public readonly ?string $filter_type = null,
        public readonly string $type_col = 'type',
        public readonly array $columns = [],
        public readonly string $row_template = '',
    ) {}

    public static function type(): string
    {
        return 'multiline_aggregate';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $rows = $ctx->sheets[$this->sheet] ?? [];
        if ($this->filter_type !== null && $this->filter_type !== '') {
            // Accept either a single type ("PHOTO") or a CSV ("NOTICE,BROCH").
            $allowed = array_map('trim', explode(',', $this->filter_type));
            $rows = array_values(array_filter(
                $rows,
                fn (array $r): bool => in_array((string) ($r[$this->type_col] ?? ''), $allowed, true),
            ));
        }

        $extract = function (array $row): array {
            if (array_is_list($this->columns)) {
                return array_intersect_key($row, array_flip($this->columns));
            }

            $out = [];
            foreach ($this->columns as $outKey => $cfg) {
                $src = is_array($cfg) ? ($cfg['source_col'] ?? $outKey) : $cfg;
                $out[$outKey] = $row[$src] ?? null;
            }

            return $out;
        };

        // Une seule colonne demandée : `first` / `last` rendent sa valeur, pas un
        // tableau à une entrée (inexploitable par les champs numériques ou texte).
        $single = static fn (array $extracted): mixed => count($extracted) === 1 ? reset($extracted) : $extracted;

        return match ($this->method) {
            'count' => count($rows),
            'first' => $rows === [] ? null : $single($extract($rows[0])),
            'last' => $rows === [] ? null : $single($extract($rows[array_key_last($rows)])),
            // Somme des valeurs de la 1ʳᵉ colonne demandée — ex. éco-participation :
            // total des lignes CONTRIB d'une feuille réglementaire (DEEE + mobilier…).
            // Décimales FR acceptées ; null si aucune valeur numérique.
            'sum' => $this->sum(array_map($extract, $rows)),
            // `{COLONNE}` remplacé par la valeur de la ligne, lignes jointes par le séparateur.
            'template' => implode($this->separator, array_map(
                fn (array $row): string => (string) preg_replace_callback(
                    '/\{([^{}]+)\}/',
                    static fn (array $m): string => is_scalar($row[$m[1]] ?? null) ? (string) $row[$m[1]] : '',
                    $this->row_template,
                ),
                $rows,
            )),
            'json_array' => json_encode(array_map($extract, $rows), JSON_UNESCAPED_UNICODE),
            'concat' => implode($this->separator, array_map(
                static fn (array $r): string => implode(' ', array_map('strval', $r)),
                array_map($extract, $rows),
            )),
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $extracted
     */
    private function sum(array $extracted): ?float
    {
        $total = null;
        foreach ($extracted as $row) {
            $value = NumberParser::isBlank(reset($row)) ? null : NumberParser::toFloat(reset($row));
            if ($value !== null) {
                $total = ($total ?? 0.0) + $value;
            }
        }

        return $total === null ? null : round($total, 6);
    }
}

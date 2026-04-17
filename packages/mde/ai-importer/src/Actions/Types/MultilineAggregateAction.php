<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

/**
 * Aggregate secondary-sheet rows (1-N join) into a single string/array/count.
 *
 * The secondary sheet must be pre-indexed by `join_key` in `ctx->sheets`.
 * Each entry is a row array; `filter_type` restricts to rows where the
 * `type` column matches (e.g. only "CODE_IMAGE" rows).
 */
final class MultilineAggregateAction extends Action
{
    /**
     * @param  array<string, array<string, mixed>>|array<int, string>  $columns
     */
    public function __construct(
        public readonly string $sheet = '',
        public readonly string $method = 'concat', // first|last|count|concat|json_array
        public readonly string $separator = '|',
        public readonly ?string $filter_type = null,
        public readonly array $columns = [],
    ) {}

    public static function type(): string
    {
        return 'multiline_aggregate';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $rows = $ctx->sheets[$this->sheet] ?? [];
        if ($this->filter_type !== null) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $r): bool => ($r['type'] ?? null) === $this->filter_type,
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

        return match ($this->method) {
            'count' => count($rows),
            'first' => $rows === [] ? null : $extract($rows[0]),
            'last' => $rows === [] ? null : $extract($rows[array_key_last($rows)]),
            'json_array' => json_encode(array_map($extract, $rows), JSON_UNESCAPED_UNICODE),
            'concat' => implode($this->separator, array_map(
                static fn (array $r): string => implode(' ', array_map('strval', $r)),
                array_map($extract, $rows),
            )),
            default => null,
        };
    }
}

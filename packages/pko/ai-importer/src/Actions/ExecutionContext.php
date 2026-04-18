<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions;

use Pko\AiImporter\Models\ImportJob;

/**
 * Carries per-row execution state through the pipeline.
 *
 * Actions read `row` to access other source columns (concat/template), and
 * `sheets` to reach secondary sheets (multiline_aggregate). `job` gives
 * access to config, LLM selection, and logger.
 */
final class ExecutionContext
{
    /**
     * @param  array<string, mixed>  $row  columns of the current row (primary sheet)
     * @param  array<string, array<int, array<string, mixed>>>  $sheets  indexed secondary sheets keyed by join key
     * @param  array<string, mixed>  $mappedOutput  values already computed during this row's pipeline
     */
    public function __construct(
        public readonly ImportJob $job,
        public array $row = [],
        public array $sheets = [],
        public array $mappedOutput = [],
        public int $rowNumber = 0,
    ) {}

    public function setOutput(string $key, mixed $value): void
    {
        $this->mappedOutput[$key] = $value;
    }

    public function getOutput(string $key, mixed $default = null): mixed
    {
        return $this->mappedOutput[$key] ?? $default;
    }
}

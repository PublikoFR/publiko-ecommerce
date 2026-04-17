<?php

declare(strict_types=1);

namespace Mde\AiImporter\Console;

use Illuminate\Console\Command;
use Mde\AiImporter\Actions\ExecutionContext;
use Mde\AiImporter\Models\ImporterConfig;
use Mde\AiImporter\Models\ImportJob;
use Mde\AiImporter\Services\ActionPipeline;
use Mde\AiImporter\Services\SpreadsheetParser;

/**
 * Dry-run a config against a real file without touching the DB or queueing
 * a full parse job. Useful to debug a mapping without creating 50k staging
 * rows first.
 *
 * Example:
 *   make artisan CMD='ai-importer:preview-config somfy /tmp/catalog.xlsx --rows=5'
 */
class PreviewConfigCommand extends Command
{
    protected $signature = 'ai-importer:preview-config
                            {config : Name of the ImporterConfig (or `file:` + path to a raw JSON)}
                            {file : Path to the spreadsheet to preview}
                            {--rows=5 : Max rows to preview}';

    protected $description = 'Exécute une config sur les N premières lignes d\'un fichier, sans DB.';

    public function handle(SpreadsheetParser $parser, ActionPipeline $pipeline): int
    {
        $configIdOrName = (string) $this->argument('config');
        $file = (string) $this->argument('file');
        $rows = max(1, (int) $this->option('rows'));

        if (! is_file($file)) {
            $this->error("Fichier introuvable : {$file}");

            return self::FAILURE;
        }

        $configData = str_starts_with($configIdOrName, 'file:')
            ? json_decode((string) file_get_contents(substr($configIdOrName, 5)), true)
            : optional(ImporterConfig::query()->where('name', $configIdOrName)->first())->config_data;

        if (! $configData) {
            $this->error("Config introuvable : {$configIdOrName}");

            return self::FAILURE;
        }

        $configArr = $configData instanceof \ArrayObject ? $configData->getArrayCopy() : (array) $configData;
        $parser->load($file, $configArr);
        $primary = $parser->primarySheetName();
        $mapping = (array) ($configArr['mapping'] ?? []);
        $joinKey = $parser->joinKeyName();

        $placeholder = new ImportJob;
        $count = 0;
        $headers = ['#', ...array_keys($mapping)];
        $rowsData = [];

        foreach ($parser->iterateRows($primary) as $rowNumber => $row) {
            if ($count >= $rows) {
                break;
            }
            $sheets = $joinKey !== null ? $parser->secondarySheetsFor($row[$joinKey] ?? '') : [];
            $ctx = new ExecutionContext(job: $placeholder, row: $row, sheets: $sheets, rowNumber: $rowNumber);

            $output = [$rowNumber];
            foreach ($mapping as $key => $columnConfig) {
                $srcCol = $columnConfig['col'] ?? null;
                $initial = $srcCol !== null ? ($row[$srcCol] ?? null) : null;
                $value = $pipeline->run($initial, (array) $columnConfig, $ctx);
                $ctx->setOutput((string) $key, $value);
                $output[] = $this->stringify($value);
            }
            $rowsData[] = $output;
            $count++;
        }

        $this->table($headers, $rowsData);
        $this->info("Preview terminée — {$count} ligne(s).");

        return self::SUCCESS;
    }

    private function stringify(mixed $v): string
    {
        if (is_scalar($v) || $v === null) {
            return (string) $v;
        }

        return substr((string) json_encode($v, JSON_UNESCAPED_UNICODE), 0, 60);
    }
}

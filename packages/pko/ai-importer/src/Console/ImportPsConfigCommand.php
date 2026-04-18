<?php

declare(strict_types=1);

namespace Pko\AiImporter\Console;

use Illuminate\Console\Command;
use Pko\AiImporter\Models\ImporterConfig;

/**
 * Migrates an existing PrestaShop `publikoaiimporter` JSON config file into
 * an `ImporterConfig` row.
 *
 * The PS format is already v1 pipeline (`actions: []`), so this command
 * does only two things:
 *
 *  1. Validate the top-level schema (`mapping`, optional `sheets`, etc.).
 *  2. Strip a handful of PS-only keys (`id_category_default`, column letters
 *     baked into `col`) — those are preserved as-is for now, the writer
 *     contract in `LunarProductWriter` gates what actually reaches Lunar.
 *
 * Column-letter mappings still work because `SpreadsheetParser` aliases
 * every row by its letter *and* its header name.
 */
class ImportPsConfigCommand extends Command
{
    protected $signature = 'ai-importer:import-ps-config
                            {file : Absolute path to the PS JSON config}
                            {--name= : Override the config name (defaults to file basename)}
                            {--supplier= : Supplier name stored on the row}
                            {--replace : Overwrite an existing config with the same name}';

    protected $description = 'Importe un fichier JSON config Publiko AI Importer (PrestaShop) dans pko_ai_importer_configs.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("Fichier introuvable : {$file}");

            return self::FAILURE;
        }

        $json = file_get_contents($file);
        $data = json_decode((string) $json, true);
        if (! is_array($data)) {
            $this->error('JSON invalide.');

            return self::FAILURE;
        }

        if (! isset($data['mapping']) || ! is_array($data['mapping'])) {
            $this->error('La config doit contenir une clé `mapping`.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: pathinfo($file, PATHINFO_FILENAME));
        $supplier = (string) ($this->option('supplier') ?: ($data['fournisseur'] ?? ''));

        $existing = ImporterConfig::query()->where('name', $name)->first();
        if ($existing && ! $this->option('replace')) {
            $this->error("Une config s'appelle déjà « {$name} ». Utilise --replace pour l'écraser.");

            return self::FAILURE;
        }

        ImporterConfig::query()->updateOrCreate(
            ['name' => $name],
            [
                'supplier_name' => $supplier ?: null,
                'description' => 'Importée depuis '.basename($file),
                'config_data' => $this->normalise($data),
            ],
        );

        $actionCount = 0;
        foreach ($data['mapping'] as $columnConfig) {
            $actionCount += count($columnConfig['actions'] ?? []);
        }

        $this->info("Config « {$name} » importée.");
        $this->line('  Colonnes mappées : '.count($data['mapping']));
        $this->line("  Actions totales : {$actionCount}");

        if (isset($data['sheets'])) {
            $this->line('  Feuilles : '.implode(', ', array_keys((array) $data['sheets'])));
        }

        return self::SUCCESS;
    }

    /**
     * Small normalisation pass — the PS format already matches ours, but we
     * make sure the legacy `action` (singular object) is lifted into a
     * single-item `actions` array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        foreach ($data['mapping'] ?? [] as $key => $column) {
            if (isset($column['action']) && ! isset($column['actions'])) {
                $data['mapping'][$key]['actions'] = [$column['action']];
                unset($data['mapping'][$key]['action']);
            }
        }

        return $data;
    }
}

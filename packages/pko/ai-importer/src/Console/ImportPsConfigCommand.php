<?php

declare(strict_types=1);

namespace Pko\AiImporter\Console;

use Illuminate\Console\Command;
use Pko\AiImporter\Actions\ActionRegistry;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\AiImporter\Support\LlmActionNormalizer;

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

        $this->reportCompatibility($data);

        return self::SUCCESS;
    }

    /**
     * Print a per-column / per-action compatibility report. Helps diagnose
     * unknown writer keys and unknown action types after import — typically
     * leftovers from PrestaShop concepts without Lunar equivalent.
     *
     * @param  array<string, mixed>  $data
     */
    private function reportCompatibility(array $data): void
    {
        // Même source de vérité que le writer : une clé qu'il sait écrire ne doit
        // jamais être annoncée « ignorée » ici.
        $writerKeys = LunarProductWriter::CANONICAL_KEYS;
        $writerAliases = LunarProductWriter::LEGACY_ALIASES + LunarProductWriter::LEGACY_EURO_ALIASES;

        $mapping = (array) $data['mapping'];
        $alias = $unknown = $unknownActions = [];

        foreach ($mapping as $key => $column) {
            $stringKey = (string) $key;
            if (in_array($stringKey, $writerKeys, true)) {
                continue;
            }
            if (isset($writerAliases[$stringKey])) {
                $alias[$stringKey] = $writerAliases[$stringKey];

                continue;
            }
            $unknown[] = $stringKey;
        }

        foreach ($mapping as $key => $column) {
            foreach ((array) ($column['actions'] ?? []) as $action) {
                $type = (string) ($action['type'] ?? '');
                if ($type === '') {
                    continue;
                }
                try {
                    ActionRegistry::resolve($type);
                } catch (\InvalidArgumentException) {
                    $unknownActions[$type][] = (string) $key;
                }
            }
        }

        $this->newLine();
        $this->line('<comment>Compatibilité writer</comment>');
        if ($alias !== []) {
            $this->line(sprintf('  <info>Aliases reconnus (%d) — auto-mappés à l\'import :</info>', count($alias)));
            foreach ($alias as $legacy => $canonical) {
                $this->line("    - {$legacy} → {$canonical}");
            }
        }
        if ($unknown !== []) {
            $this->line(sprintf('  <comment>Clés ignorées par le writer (%d) — perdues à l\'écriture Lunar :</comment>', count($unknown)));
            foreach ($unknown as $key) {
                $this->line("    - {$key}");
            }
        }
        if ($alias === [] && $unknown === []) {
            $this->line('  <info>Toutes les clés du mapping correspondent à des clés natives Lunar.</info>');
        }

        if ($unknownActions !== []) {
            $this->newLine();
            $this->line('<error>Actions inconnues (lèveront une exception au runtime) :</error>');
            foreach ($unknownActions as $type => $usedIn) {
                $this->line('  - <error>'.$type.'</error> (utilisée par : '.implode(', ', array_unique($usedIn)).')');
            }
        }
    }

    /**
     * Small normalisation pass — the PS format already matches ours, but we
     * make sure the legacy `action` (singular object) is lifted into a
     * single-item `actions` array, and that the PS top-level LLM context keys
     * (`llm_global_context` / `llm_cache_context`) land in the `ai` section
     * read by `LlmTransformAction` — otherwise the global context of a PS
     * config was silently dropped.
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

        // Ordre des colonnes du fichier JSON, conservé à part : la colonne JSON de
        // MySQL réordonne les clés du mapping (cf. MappingOrder::declared()). Un
        // `mapping_order` déjà présent (config exportée d'ici) est repris tel quel.
        if (empty($data['mapping_order']) || ! is_array($data['mapping_order'])) {
            $data['mapping_order'] = array_map('strval', array_keys((array) ($data['mapping'] ?? [])));
        }

        $ai = (array) ($data['ai'] ?? []);
        if (array_key_exists('llm_global_context', $data)) {
            $context = $data['llm_global_context'];
            $context = is_string($context)
                ? trim($context)
                : trim((string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($context !== '' && trim((string) ($ai['global_context'] ?? '')) === '') {
                $ai['global_context'] = $context;
            }
            unset($data['llm_global_context']);
        }
        if (array_key_exists('llm_cache_context', $data)) {
            if (! array_key_exists('context_cache', $ai)) {
                $ai['context_cache'] = (bool) $data['llm_cache_context'];
            }
            unset($data['llm_cache_context']);
        }
        if ($ai !== []) {
            $data['ai'] = $ai;
        }

        // `llm_config_id` du module d'origine → fournisseur + alias portables.
        return LlmActionNormalizer::config($data);
    }
}

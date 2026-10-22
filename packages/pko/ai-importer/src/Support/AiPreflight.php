<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use Filament\Notifications\Notification;
use Pko\AiCore\Llm\LlmManager;
use Pko\AiCore\Models\LlmConfig;
use Pko\AiImporter\Models\ImporterConfig;

/**
 * Contrôle bloquant avant une préparation : chaque fournisseur / modèle IA
 * utilisé par la config doit avoir une clé active et répondre à un appel de
 * test minimal. Évite de lancer un parse de milliers de lignes facturables
 * dont les colonnes IA resteraient vides (modèle introuvable, clé absente,
 * paramètre refusé par une nouvelle génération de modèle…).
 *
 * Un appel de test par couple fournisseur/modèle distinct (quelques tokens).
 */
final class AiPreflight
{
    /**
     * Contrôle d'une préparation à lancer, avec notification bloquante si besoin.
     *
     * @param  array<int, string>  $columns
     * @return bool true si la préparation peut partir
     */
    public static function allowsParse(?ImporterConfig $config, array $columns = []): bool
    {
        if ($config === null) {
            return true;
        }

        $data = $config->config_data instanceof \ArrayObject ? $config->config_data->getArrayCopy() : (array) $config->config_data;
        $errors = self::check($data, array_values(array_map('strval', $columns)));
        if ($errors === []) {
            return true;
        }

        Notification::make()
            ->danger()
            ->persistent()
            ->title('Préparation bloquée : IA inutilisable')
            ->body(implode("\n\n", $errors)."\n\nCorrigez la configuration IA (Configurations LLM) ou le fournisseur / modèle de l'action, puis relancez.")
            ->send();

        return false;
    }

    /**
     * @param  array<string, mixed>  $configData  `config_data` de l'ImporterConfig
     * @param  array<int, string>  $columns  colonnes réellement calculées (vide = toutes)
     * @return array<int, string> messages d'erreur, vide si tout est utilisable
     */
    public static function check(array $configData, array $columns = []): array
    {
        $configData = LlmActionNormalizer::config($configData);
        $mapping = (array) ($configData['mapping'] ?? []);
        if ($columns !== []) {
            $mapping = array_intersect_key($mapping, array_flip($columns));
        }

        /** @var array<string, array{backend: string, model: string, columns: array<int, string>}> $targets */
        $targets = [];
        foreach ($mapping as $column => $columnConfig) {
            foreach (self::llmActions((array) ($columnConfig['actions'] ?? [])) as $action) {
                $backend = trim((string) ($action['backend'] ?? ''));
                $model = trim((string) ($action['model'] ?? ''));
                $key = $backend.'|'.$model;
                $targets[$key] ??= ['backend' => $backend, 'model' => $model, 'columns' => []];
                $targets[$key]['columns'][] = (string) $column;
            }
        }

        $errors = [];
        foreach ($targets as $target) {
            $label = $target['backend'] !== ''
                ? $target['backend'].($target['model'] !== '' ? ' / '.$target['model'] : '')
                : 'config IA par défaut';
            $where = 'Colonne(s) '.implode(', ', array_unique($target['columns']))." ({$label})";

            try {
                $manager = app(LlmManager::class);
                if ($target['backend'] !== '') {
                    $provider = $manager->forBackend($target['backend'], $target['model'] !== '' ? $target['model'] : null);
                } else {
                    $default = LlmConfig::default();
                    if ($default === null) {
                        $errors[] = "{$where} : aucune configuration IA par défaut active.";

                        continue;
                    }
                    $provider = $manager->forConfig($default);
                }
                $provider->testConnection();
            } catch (\Throwable $e) {
                $errors[] = "{$where} : ".mb_strimwidth($e->getMessage(), 0, 300, '…');
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $actions
     * @return array<int, array<string, mixed>>
     */
    private static function llmActions(array $actions): array
    {
        $found = [];
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }
            if (($action['type'] ?? null) === 'llm_transform') {
                $found[] = $action;
            }
            foreach (['actions', 'else_actions'] as $nested) {
                array_push($found, ...self::llmActions((array) ($action[$nested] ?? [])));
            }
            foreach ((array) ($action['branches'] ?? []) as $branch) {
                array_push($found, ...self::llmActions((array) ($branch['actions'] ?? [])));
            }
        }

        return $found;
    }
}

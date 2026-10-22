<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use Pko\AiCore\Models\LlmConfig;

/**
 * Convertit les actions IA désignant une configuration par son id en base
 * (`llm_config_id`, propre à un environnement) en fournisseur + modèle
 * portables (`backend: claude`, `model: sonnet`).
 *
 * Un id introuvable est simplement retiré : l'action suit alors la
 * configuration IA par défaut. Les branches de `condition` sont parcourues.
 */
final class LlmActionNormalizer
{
    private const CLAUDE_FAMILY = '/^claude-(fable|opus|sonnet|haiku)-/';

    /**
     * @param  array<string, mixed>  $config  `config_data` complet
     * @return array<string, mixed>
     */
    public static function config(array $config): array
    {
        foreach ((array) ($config['mapping'] ?? []) as $key => $column) {
            if (is_array($column) && isset($column['actions']) && is_array($column['actions'])) {
                $config['mapping'][$key]['actions'] = self::actions($column['actions']);
            }
        }

        return $config;
    }

    /**
     * @param  array<int, mixed>  $actions
     * @return array<int, mixed>
     */
    public static function actions(array $actions): array
    {
        foreach ($actions as $i => $action) {
            if (! is_array($action)) {
                continue;
            }
            foreach (['actions', 'else_actions'] as $nested) {
                if (isset($action[$nested]) && is_array($action[$nested])) {
                    $action[$nested] = self::actions($action[$nested]);
                }
            }
            foreach ((array) ($action['branches'] ?? []) as $b => $branch) {
                if (is_array($branch) && isset($branch['actions']) && is_array($branch['actions'])) {
                    $action['branches'][$b]['actions'] = self::actions($branch['actions']);
                }
            }
            $actions[$i] = self::action($action);
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    public static function action(array $action): array
    {
        if (($action['type'] ?? null) !== 'llm_transform' || ! array_key_exists('llm_config_id', $action)) {
            return $action;
        }

        $id = $action['llm_config_id'];
        unset($action['llm_config_id']);

        if (! empty($action['backend']) || ! is_numeric($id) || (int) $id <= 0 || ! class_exists(LlmConfig::class)) {
            return $action;
        }

        $config = LlmConfig::query()->find((int) $id);
        if ($config === null) {
            return $action;
        }

        $action['backend'] = $config->provider->value;
        $action['model'] = preg_match(self::CLAUDE_FAMILY, (string) $config->model, $m) ? $m[1] : (string) $config->model;

        return $action;
    }
}

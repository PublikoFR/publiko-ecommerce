<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use ArrayObject;
use Pko\AiImporter\Models\ImporterConfig;

/**
 * Extrait, depuis le `config_data.mapping` d'une config, la liste des colonnes
 * « à traiter » telles qu'affichées par la page « Préparer un fichier ».
 *
 * Portage fidèle de `AdminPublikoImportController::ajaxProcessGetConfigColumns`
 * (module PrestaShop) :
 *
 *  - on ignore les colonnes sans `col` source ET sans `actions` (valeur par
 *    défaut statique → rien à calculer au parse) ;
 *  - une colonne est marquée « IA » dès qu'une de ses actions est de type
 *    `llm_transform` ;
 *  - le libellé reprend la clé de mapping + sa source (`sheet:col`, `colonne X`
 *    ou `défaut: …`) ;
 *  - tri alphabétique sur le libellé.
 */
final class ConfigColumnExtractor
{
    /**
     * @return array<int, array{value: string, label: string, has_ai: bool}>
     */
    public static function fromConfig(?ImporterConfig $config): array
    {
        if (! $config) {
            return [];
        }

        return self::fromMapping(self::mappingOf($config));
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array<int, array{value: string, label: string, has_ai: bool}>
     */
    public static function fromMapping(array $mapping): array
    {
        $columns = [];

        foreach ($mapping as $key => $data) {
            if (! is_array($data)) {
                continue;
            }

            $hasCol = ! empty($data['col']);
            $actions = self::actionsOf($data);
            $hasActions = $actions !== [];
            $hasDefault = array_key_exists('default', $data);

            // Une colonne uniquement « default » est statique : rien à préparer.
            if (! $hasCol && ! $hasActions) {
                continue;
            }

            $hasAi = false;
            foreach ($actions as $action) {
                if (is_array($action) && ($action['type'] ?? null) === 'llm_transform') {
                    $hasAi = true;
                    break;
                }
            }

            $source = '';
            if ($hasCol && ! empty($data['sheet'])) {
                $source = $data['sheet'].':'.$data['col'];
            } elseif ($hasCol) {
                $source = 'colonne '.$data['col'];
            }
            if ($hasDefault) {
                $defaultStr = 'défaut: '.(is_scalar($data['default']) ? (string) $data['default'] : '…');
                $source = $source !== '' ? $source.', '.$defaultStr : $defaultStr;
            }

            $columns[] = [
                'value' => (string) $key,
                'label' => $source !== '' ? (string) $key.' ('.$source.')' : (string) $key,
                'has_ai' => $hasAi,
            ];
        }

        usort($columns, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $columns;
    }

    /**
     * Clés de mapping marquées « IA » (au moins une action `llm_transform`).
     *
     * @return array<int, string>
     */
    public static function aiColumnKeys(?ImporterConfig $config): array
    {
        return array_values(array_map(
            static fn (array $c): string => $c['value'],
            array_filter(self::fromConfig($config), static fn (array $c): bool => $c['has_ai']),
        ));
    }

    /**
     * Toutes les clés traitables (pour pré-cocher la grille).
     *
     * @return array<int, string>
     */
    public static function allColumnKeys(?ImporterConfig $config): array
    {
        return array_map(static fn (array $c): string => $c['value'], self::fromConfig($config));
    }

    /**
     * @return array<string, mixed>
     */
    private static function mappingOf(ImporterConfig $config): array
    {
        $data = $config->config_data;
        if ($data instanceof ArrayObject) {
            $data = $data->getArrayCopy();
        }

        return is_array($data) ? (array) ($data['mapping'] ?? []) : [];
    }

    /**
     * Normalise `actions` (pipeline v1) ET `action` (objet unique legacy v0).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    private static function actionsOf(array $data): array
    {
        if (! empty($data['actions']) && is_array($data['actions'])) {
            return array_values($data['actions']);
        }
        if (! empty($data['action']) && is_array($data['action'])) {
            return [$data['action']];
        }

        return [];
    }
}

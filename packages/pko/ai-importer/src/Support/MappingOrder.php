<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

/**
 * Ordre de calcul des colonnes d'un mapping.
 *
 * Les `input_columns` d'une action `llm_transform` désignent des CLÉS DE SORTIE
 * du mapping (`name`, `manufacturer`, `description_short`…), c'est-à-dire des
 * valeurs déjà transformées — pas des colonnes brutes du fichier. Pour qu'elles
 * soient disponibles, les colonnes sans IA sont calculées d'abord (dans l'ordre
 * du mapping), puis les colonnes IA, elles-mêmes ordonnées pour qu'une colonne
 * IA qui en lit une autre (`features` ← `description`) passe après elle.
 * Un cycle entre colonnes IA retombe sur l'ordre du mapping.
 */
final class MappingOrder
{
    /**
     * Mapping d'une config dans l'ordre CHOISI dans l'éditeur.
     *
     * La base range `config_data` dans une colonne JSON, et MySQL réordonne les
     * clés d'un objet JSON (par longueur puis ordre alphabétique) : l'ordre des
     * colonnes posé dans l'éditeur était perdu à chaque sauvegarde (`id`, `mpn`,
     * `name`… au lieu de l'ordre saisi). L'ordre est donc conservé à part, dans
     * la liste `config_data.mapping_order` (un tableau JSON garde son ordre) ;
     * les clés absentes de la liste suivent, dans l'ordre de l'objet.
     *
     * @param  array<string, mixed>  $config  `config_data` complet
     * @return array<string, mixed>
     */
    public static function declared(array $config): array
    {
        $mapping = (array) ($config['mapping'] ?? []);
        $order = array_values(array_filter((array) ($config['mapping_order'] ?? []), 'is_scalar'));
        if ($order === []) {
            return $mapping;
        }

        $ordered = [];
        foreach ($order as $key) {
            $key = (string) $key;
            if (array_key_exists($key, $mapping)) {
                $ordered[$key] = $mapping[$key];
            }
        }

        return $ordered + $mapping;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array<string, mixed> même mapping, clés réordonnées
     */
    public static function sorted(array $mapping): array
    {
        $plain = [];
        $llm = [];
        $deps = [];

        foreach ($mapping as $key => $column) {
            $inputs = self::llmInputs($column);
            if ($inputs === null) {
                $plain[(string) $key] = $column;
            } else {
                $llm[(string) $key] = $column;
                $deps[(string) $key] = $inputs;
            }
        }

        if ($llm === []) {
            return $mapping;
        }

        // Tri topologique stable entre colonnes IA (dépendances vers d'autres
        // colonnes IA uniquement ; le reste est déjà calculé).
        $ordered = [];
        $remaining = $llm;
        while ($remaining !== []) {
            $progressed = false;
            foreach ($remaining as $key => $column) {
                $pending = array_filter(
                    $deps[$key],
                    static fn (string $d): bool => $d !== $key && isset($remaining[$d]),
                );
                if ($pending === []) {
                    $ordered[$key] = $column;
                    unset($remaining[$key]);
                    $progressed = true;
                }
            }
            if (! $progressed) {
                // Cycle : on vide dans l'ordre du mapping.
                foreach ($remaining as $key => $column) {
                    $ordered[$key] = $column;
                }
                break;
            }
        }

        return $plain + $ordered;
    }

    /**
     * Clés lues par les actions `llm_transform` de la colonne, ou null si la
     * colonne n'en contient aucune.
     *
     * @return array<int, string>|null
     */
    private static function llmInputs(mixed $column): ?array
    {
        if (! is_array($column)) {
            return null;
        }

        $actions = $column['actions'] ?? (isset($column['action']) ? [$column['action']] : []);
        $inputs = null;
        foreach ((array) $actions as $action) {
            if (! is_array($action) || ($action['type'] ?? null) !== 'llm_transform') {
                continue;
            }
            $inputs ??= [];
            foreach ((array) ($action['input_columns'] ?? []) as $col) {
                if (is_array($col)) {
                    $col = $col['col'] ?? reset($col);
                }
                if (is_scalar($col) && (string) $col !== '') {
                    $inputs[] = (string) $col;
                }
            }
        }

        return $inputs;
    }
}

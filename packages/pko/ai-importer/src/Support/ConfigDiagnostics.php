<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use Pko\AiImporter\Services\LunarProductWriter;

/**
 * Contrôle statique d'une configuration d'import (`config_data`), sans fichier :
 * ce qui empêcherait une préparation d'aboutir ou donnerait un staging vide.
 *
 * Affiché dans l'éditeur de configuration et à l'étape 1 d'un nouvel import,
 * pour que l'erreur soit vue AVANT de lancer un traitement de plusieurs minutes.
 *
 * Chaque constat : `['level' => error|warning|info, 'message' => string]`.
 */
final class ConfigDiagnostics
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const INFO = 'info';

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array{level: string, message: string}>
     */
    public static function analyze(array $config): array
    {
        $findings = [];
        $mapping = array_filter((array) ($config['mapping'] ?? []), 'is_array');
        $sheets = array_filter((array) ($config['sheets'] ?? []), 'is_array');
        $primary = (string) ($config['primary_sheet'] ?? '');

        if ($mapping === []) {
            return [self::finding(self::ERROR, 'Aucune colonne mappée : la préparation ne produirait rien.')];
        }

        $keys = array_keys(LunarProductWriter::normalizeLegacyKeys(array_fill_keys(array_map('strval', array_keys($mapping)), '1')));

        // Identification du produit.
        $hasIdentity = in_array('sku', $keys, true)
            || in_array('reference', $keys, true)
            || (in_array('brand_name', $keys, true) && in_array('mpn', $keys, true));
        if (! $hasIdentity) {
            $findings[] = self::finding(self::ERROR, 'Aucune colonne d\'identification : mappez « Référence », « SKU forcé » ou « Marque » + « MPN ». Sans elle, chaque ligne est rejetée à l\'import.');
        }
        if (! in_array('name', $keys, true)) {
            $findings[] = self::finding(self::WARNING, 'Colonne « Nom » non mappée : les produits absents du catalogue ne pourront pas être créés (mise à jour seule).');
        }

        // En-têtes désactivés alors que des colonnes sont désignées par leur intitulé.
        foreach (self::sheetsWithoutHeaderButNamedColumns($config) as $sheet => $columns) {
            $findings[] = self::finding(self::ERROR, sprintf(
                'Feuille « %s » : « 1ʳᵉ ligne = en-têtes » est décoché, mais %s désignée(s) par leur intitulé (%s). Elles seront introuvables et la ligne d\'en-têtes sera importée comme un produit. Cochez l\'option ou désignez les colonnes par leur lettre.',
                $sheet,
                count($columns) > 1 ? 'des colonnes sont' : 'une colonne est',
                implode(', ', array_slice($columns, 0, 6)).(count($columns) > 6 ? '…' : ''),
            ));
        }

        // Feuilles référencées mais non déclarées.
        $declared = array_map('strval', array_keys($sheets));
        $unknownSheets = [];
        foreach ($mapping as $key => $column) {
            $sheet = (string) ($column['sheet'] ?? '');
            if ($sheet !== '' && $sheet !== $primary && ! in_array($sheet, $declared, true)) {
                $unknownSheets[$sheet][] = (string) $key;
            }
        }
        foreach ($unknownSheets as $sheet => $columns) {
            $findings[] = self::finding(self::ERROR, sprintf(
                'Feuille « %s » utilisée par %s mais absente des « Feuilles avec relations » : ces colonnes resteront vides.',
                $sheet,
                implode(', ', array_slice($columns, 0, 6)).(count($columns) > 6 ? '…' : ''),
            ));
        }

        // Jointure.
        $secondary = array_diff($declared, [$primary]);
        if ($secondary !== [] && blank($config['join_key'] ?? null)) {
            $findings[] = self::finding(self::ERROR, 'Des feuilles liées sont déclarées mais la « clé de jointure globale » est vide : aucune ligne ne sera rapprochée.');
        }

        // Colonnes sans effet à l'import.
        $ignored = self::ignoredKeys(array_map('strval', array_keys($mapping)));
        if ($ignored !== []) {
            $findings[] = self::finding(self::INFO, sprintf(
                '%d colonne(s) préparée(s) mais non écrite(s) dans le catalogue (pas de champ correspondant) : %s.',
                count($ignored),
                implode(', ', array_slice($ignored, 0, 12)).(count($ignored) > 12 ? '…' : ''),
            ));
        }

        $ai = array_column(array_filter(ConfigColumnExtractor::fromMapping($mapping), static fn (array $c): bool => $c['has_ai']), 'value');
        if ($ai !== []) {
            $findings[] = self::finding(self::INFO, sprintf(
                '%d colonne(s) calculée(s) par IA (un appel facturé par ligne et par colonne) : %s.',
                count($ai),
                implode(', ', $ai),
            ));
        }

        return $findings;
    }

    /**
     * @param  array<int, array{level: string, message: string}>  $findings
     */
    public static function hasErrors(array $findings): bool
    {
        foreach ($findings as $finding) {
            if ($finding['level'] === self::ERROR) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clés de mapping que le writer n'écrit nulle part (ni canoniques ni alias).
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public static function ignoredKeys(array $keys): array
    {
        $known = array_flip([
            ...LunarProductWriter::CANONICAL_KEYS,
            ...array_keys(LunarProductWriter::LEGACY_ALIASES),
            ...array_keys(LunarProductWriter::LEGACY_EURO_ALIASES),
        ]);

        return array_values(array_filter($keys, static fn (string $key): bool => ! isset($known[$key])));
    }

    /**
     * Feuilles dont l'en-tête est désactivé (`skip_first_row: false`) alors que
     * la config y désigne des colonnes par leur intitulé (pas par une lettre).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, array<int, string>> feuille → intitulés utilisés
     */
    public static function sheetsWithoutHeaderButNamedColumns(array $config): array
    {
        $sheets = array_filter((array) ($config['sheets'] ?? []), 'is_array');
        $primary = (string) ($config['primary_sheet'] ?? '');
        $out = [];

        foreach ($sheets as $name => $sheet) {
            $name = (string) $name;
            if (($sheet['skip_first_row'] ?? true) !== false) {
                continue;
            }

            $named = [];
            foreach (['join_col', 'join_key', 'type_col'] as $key) {
                if (self::isNamedColumn($sheet[$key] ?? null)) {
                    $named[] = (string) $sheet[$key];
                }
            }
            if ($name === $primary && self::isNamedColumn($config['join_key'] ?? null)) {
                $named[] = (string) $config['join_key'];
            }
            foreach ((array) ($config['mapping'] ?? []) as $column) {
                if (! is_array($column)) {
                    continue;
                }
                $sheetOfColumn = (string) ($column['sheet'] ?? '');
                $targetsSheet = $sheetOfColumn === $name || ($sheetOfColumn === '' && $name === $primary);
                if ($targetsSheet && self::isNamedColumn($column['col'] ?? null)) {
                    $named[] = (string) $column['col'];
                }
            }

            if ($named !== []) {
                $out[$name] = array_values(array_unique($named));
            }
        }

        return $out;
    }

    /**
     * Colonnes désignées par leur intitulé, par feuille (feuille primaire
     * comprise) : celles qu'il faut retrouver dans la ligne d'en-têtes du fichier.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, array<int, string>> feuille → intitulés attendus
     */
    public static function namedColumnsBySheet(array $config, string $primary): array
    {
        $out = [];
        $add = static function (string $sheet, mixed $column) use (&$out): void {
            if ($sheet !== '' && self::isNamedColumn($column)) {
                $out[$sheet][] = trim((string) $column);
            }
        };

        $add($primary, $config['join_key'] ?? null);

        foreach (array_filter((array) ($config['sheets'] ?? []), 'is_array') as $name => $sheet) {
            $name = (string) $name;
            if ($name !== $primary) {
                $add($name, $sheet['join_col'] ?? $sheet['join_key'] ?? ($config['join_key'] ?? null));
            }
            $add($name, $sheet['type_col'] ?? null);
        }

        foreach ((array) ($config['mapping'] ?? []) as $column) {
            if (! is_array($column)) {
                continue;
            }
            $sheet = (string) ($column['sheet'] ?? '');
            $add($sheet !== '' ? $sheet : $primary, $column['col'] ?? null);
        }

        return array_map(static fn (array $columns): array => array_values(array_unique($columns)), $out);
    }

    /**
     * Répare le défaut « en-têtes désactivés » posé à tort par l'ancien éditeur
     * visuel : quand TOUTES les feuilles ont `skip_first_row: false` et qu'au
     * moins une désigne des colonnes par leur intitulé (preuve qu'elle a bien une
     * ligne d'en-têtes), la clé est retirée partout (défaut du parser : en-têtes).
     *
     * @param  array<string, mixed>  $config
     * @return array{0: array<string, mixed>, 1: bool} [config, modifiée ?]
     */
    public static function repairHeaderFlags(array $config): array
    {
        $sheets = array_filter((array) ($config['sheets'] ?? []), 'is_array');
        if ($sheets === []) {
            return [$config, false];
        }

        foreach ($sheets as $sheet) {
            if (($sheet['skip_first_row'] ?? true) !== false) {
                return [$config, false];
            }
        }

        if (self::sheetsWithoutHeaderButNamedColumns($config) === []) {
            return [$config, false];
        }

        foreach (array_keys($sheets) as $name) {
            unset($config['sheets'][$name]['skip_first_row']);
        }

        return [$config, true];
    }

    /** Une colonne désignée par un intitulé (« REFCIALE ») et non par une lettre (« B », « AA »). */
    public static function isNamedColumn(mixed $column): bool
    {
        if (! is_scalar($column)) {
            return false;
        }
        $column = trim((string) $column);

        return $column !== '' && preg_match('/^[A-Z]{1,3}$/', $column) !== 1;
    }

    /**
     * @return array{level: string, message: string}
     */
    private static function finding(string $level, string $message): array
    {
        return ['level' => $level, 'message' => $message];
    }
}

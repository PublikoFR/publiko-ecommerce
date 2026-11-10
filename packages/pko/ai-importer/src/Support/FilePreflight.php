<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Pko\AiImporter\Services\ChunkReadFilter;

/**
 * Contrôle d'un fichier contre une configuration AVANT la préparation : les
 * feuilles attendues existent-elles, les colonnes désignées par leur intitulé
 * sont-elles dans la ligne d'en-têtes ? Ne lit que la 1ʳᵉ ligne de chaque
 * feuille (aucune action exécutée, aucun appel IA).
 *
 * Résultat : `sheets` (feuille → nombre de lignes de données), `findings`
 * (mêmes niveaux que {@see ConfigDiagnostics}).
 */
final class FilePreflight
{
    /** Au-delà, le contrôle est sauté (lecture trop longue pour une requête web). */
    public const MAX_BYTES = 25 * 1024 * 1024;

    /**
     * @param  array<string, mixed>  $config
     * @return array{sheets: array<string, int>, findings: array<int, array{level: string, message: string}>}
     */
    public static function check(string $absolutePath, array $config): array
    {
        if (! is_file($absolutePath)) {
            return self::result([], [self::finding(ConfigDiagnostics::ERROR, 'Fichier introuvable.')]);
        }
        if ((int) filesize($absolutePath) > self::MAX_BYTES) {
            return self::result([], [self::finding(ConfigDiagnostics::INFO, 'Fichier volumineux : contrôle des feuilles et colonnes non effectué (il le sera au lancement de la préparation).')]);
        }

        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
            if (! $reader instanceof BaseReader) {
                throw new \RuntimeException('format non pris en charge');
            }
            $reader->setReadDataOnly(true);

            $rowCounts = [];
            foreach ($reader->listWorksheetInfo($absolutePath) as $info) {
                $rowCounts[(string) $info['worksheetName']] = (int) $info['totalRows'];
            }

            // 1ʳᵉ ligne de chaque feuille seulement.
            $reader->setReadFilter(new ChunkReadFilter(1, 1));
            $spreadsheet = $reader->load($absolutePath);
        } catch (\Throwable $e) {
            return self::result([], [self::finding(ConfigDiagnostics::ERROR, 'Fichier illisible : '.mb_strimwidth($e->getMessage(), 0, 200, '…'))]);
        }

        $sheetNames = array_keys($rowCounts);
        $primary = (string) ($config['primary_sheet'] ?? '');
        if ($primary === '') {
            $primary = (string) ($sheetNames[0] ?? '');
        }

        $findings = [];
        $declared = array_map('strval', array_keys(array_filter((array) ($config['sheets'] ?? []), 'is_array')));
        $expected = array_values(array_unique(array_filter([$primary, ...$declared], static fn (string $n): bool => $n !== '')));

        foreach ($expected as $name) {
            if (! in_array($name, $sheetNames, true)) {
                $findings[] = self::finding(ConfigDiagnostics::ERROR, sprintf(
                    'Feuille « %s » absente du fichier (feuilles trouvées : %s).',
                    $name,
                    implode(', ', $sheetNames) ?: 'aucune',
                ));
            }
        }

        $sheets = [];
        foreach ($expected as $name) {
            if (! in_array($name, $sheetNames, true)) {
                continue;
            }
            $hasHeader = (bool) ($config['sheets'][$name]['skip_first_row'] ?? true);
            $sheets[$name] = max(0, $rowCounts[$name] - ($hasHeader ? 1 : 0));
        }

        foreach (ConfigDiagnostics::namedColumnsBySheet($config, $primary) as $name => $columns) {
            $sheet = $spreadsheet->getSheetByName($name);
            if ($sheet === null) {
                continue; // feuille absente : déjà signalée
            }
            if (! (bool) ($config['sheets'][$name]['skip_first_row'] ?? true)) {
                continue; // en-têtes désactivés : signalé par ConfigDiagnostics
            }

            $headers = [];
            $highest = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for ($c = 1; $c <= $highest; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c).'1';
                if (! $sheet->cellExists($coord)) {
                    continue;
                }
                $value = $sheet->getCell($coord)->getValue();
                $value = $value instanceof RichText ? $value->getPlainText() : $value;
                if (is_scalar($value) && (string) $value !== '') {
                    $headers[] = (string) $value;
                }
            }

            $missing = array_values(array_diff($columns, $headers));
            if ($missing === []) {
                continue;
            }

            $lower = array_map('mb_strtolower', array_map('trim', $headers));
            $hints = [];
            foreach ($missing as $column) {
                $index = array_search(mb_strtolower($column), $lower, true);
                $hints[] = $index !== false ? "{$column} (trouvé « {$headers[$index]} » : casse ou espaces différents)" : $column;
            }

            // Une colonne de JOINTURE introuvable vide toutes les feuilles liées :
            // bloquant. Une colonne de données absente ne vide que son champ.
            $joinColumn = $name === $primary
                ? ($config['join_key'] ?? null)
                : ($config['sheets'][$name]['join_col'] ?? $config['sheets'][$name]['join_key'] ?? ($config['join_key'] ?? null));
            $breaksJoin = is_scalar($joinColumn) && in_array(trim((string) $joinColumn), $missing, true);

            $findings[] = self::finding($breaksJoin ? ConfigDiagnostics::ERROR : ConfigDiagnostics::WARNING, sprintf(
                'Feuille « %s » : colonne(s) introuvable(s) dans la ligne d\'en-têtes — %s. %s',
                $name,
                implode(', ', $hints),
                $breaksJoin
                    ? 'La colonne de jointure en fait partie : aucune ligne des feuilles liées ne serait rapprochée.'
                    : 'Les champs qui en dépendent resteront vides.',
            ));
        }

        $spreadsheet->disconnectWorksheets();

        return self::result($sheets, $findings);
    }

    /**
     * @param  array<string, int>  $sheets
     * @param  array<int, array{level: string, message: string}>  $findings
     * @return array{sheets: array<string, int>, findings: array<int, array{level: string, message: string}>}
     */
    private static function result(array $sheets, array $findings): array
    {
        return ['sheets' => $sheets, 'findings' => $findings];
    }

    /**
     * @return array{level: string, message: string}
     */
    private static function finding(string $level, string $message): array
    {
        return ['level' => $level, 'message' => $message];
    }
}

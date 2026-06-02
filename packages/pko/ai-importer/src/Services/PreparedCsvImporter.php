<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\StagingRecord;

/**
 * Importe un CSV DÉJÀ préparé directement en staging, sans configuration de
 * mapping ni appel LLM.
 *
 * Parité avec `AdminPublikoImportController::ajaxProcessImportPreparedCsv`
 * (PrestaShop) : lecture seule, séparateur « ; », 1ʳᵉ ligne = en-têtes →
 * clés du tableau `data` de chaque StagingRecord. Le job créé n'a pas de
 * `config_id` (badge « Import CSV » côté liste) et est directement en
 * statut `Parsed` puisqu'aucun parse asynchrone n'est nécessaire.
 */
final class PreparedCsvImporter
{
    public function __construct(private readonly string $delimiter = ';') {}

    /**
     * Crée un ImportJob (sans config) et insère ses StagingRecord à partir du
     * CSV stocké sur le disque applicatif.
     *
     * @param  string  $storedPath  chemin relatif au disque (retour FileUpload)
     */
    public function import(string $storedPath, ?string $name = null, ?string $disk = null): ImportJob
    {
        $disk ??= config('ai-importer.storage.disk', 'local');
        $absolutePath = Storage::disk($disk)->path($storedPath);

        $rows = $this->parse($absolutePath);
        $jobName = trim((string) $name) !== '' ? trim((string) $name) : basename($storedPath);

        return DB::transaction(function () use ($rows, $storedPath, $jobName): ImportJob {
            $job = ImportJob::create([
                'uuid' => (string) Str::uuid(),
                'config_id' => null,
                'input_file_path' => $storedPath,
                'status' => JobStatus::Parsed,
                'import_status' => ImportStatus::Pending,
                'total_rows' => count($rows),
                'processed_rows' => count($rows),
                'staging_count' => count($rows),
                'options' => ['import_name' => $jobName],
                'parse_completed_at' => now(),
            ]);

            foreach ($rows as $index => $row) {
                StagingRecord::create([
                    'import_job_id' => $job->id,
                    'row_number' => $index + 1,
                    'data' => $row,
                    'status' => StagingStatus::Pending,
                ]);
            }

            return $job;
        });
    }

    /**
     * Lit le CSV ligne à ligne (fgetcsv) en associant chaque valeur à l'en-tête
     * de sa colonne. Gère le BOM UTF-8 et ignore les lignes vides.
     *
     * @return array<int, array<string, string>>
     */
    private function parse(string $absolutePath): array
    {
        $handle = @fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier CSV : {$absolutePath}");
        }

        try {
            $headers = fgetcsv($handle, 0, $this->delimiter);
            if (! is_array($headers) || $headers === []) {
                return [];
            }

            $headers[0] = $this->stripBom((string) ($headers[0] ?? ''));
            $headers = array_map(static fn ($h): string => trim((string) $h), $headers);

            $rows = [];
            while (($line = fgetcsv($handle, 0, $this->delimiter)) !== false) {
                // fgetcsv renvoie [null] pour une ligne vide.
                if (! is_array($line) || $line === [null]) {
                    continue;
                }

                $row = [];
                foreach ($headers as $i => $key) {
                    if ($key === '') {
                        continue;
                    }
                    $row[$key] = isset($line[$i]) ? (string) $line[$i] : '';
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function stripBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }
}

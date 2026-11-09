<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Pko\AiImporter\Support\ConfigDiagnostics;

/**
 * Répare les configs d'import abîmées par l'ancien éditeur visuel : chaque
 * sauvegarde écrivait `skip_first_row: false` sur toutes les feuilles (Toggle
 * hydraté sans valeur). La ligne d'en-têtes était alors importée comme un
 * produit et les colonnes désignées par leur intitulé (REFCIALE, MTYP…)
 * introuvables — références réduites au préfixe, jointures vides.
 *
 * Ne touche qu'aux configs où le défaut est PROUVÉ (toutes les feuilles sans
 * en-tête ET des colonnes désignées par intitulé). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pko_ai_importer_configs')->orderBy('id')->each(function (object $row): void {
            $data = json_decode((string) $row->config_data, true);
            if (! is_array($data)) {
                return;
            }

            [$repaired, $changed] = ConfigDiagnostics::repairHeaderFlags($data);
            if ($changed) {
                DB::table('pko_ai_importer_configs')
                    ->where('id', $row->id)
                    ->update(['config_data' => json_encode($repaired, JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    public function down(): void
    {
        // Réparation de données : rien à défaire.
    }
};

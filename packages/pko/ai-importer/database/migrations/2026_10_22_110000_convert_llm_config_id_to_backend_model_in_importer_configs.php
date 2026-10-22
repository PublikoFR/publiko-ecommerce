<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Pko\AiImporter\Support\LlmActionNormalizer;

/**
 * Actions IA des configs d'import : `llm_config_id` (id propre à une base)
 * → `backend` + `model` (alias portable, ex. claude / sonnet). Idempotente.
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
            $converted = LlmActionNormalizer::config($data);
            if ($converted !== $data) {
                DB::table('pko_ai_importer_configs')
                    ->where('id', $row->id)
                    ->update(['config_data' => json_encode($converted, JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    public function down(): void
    {
        // Conversion sans perte d'intention : rien à défaire.
    }
};

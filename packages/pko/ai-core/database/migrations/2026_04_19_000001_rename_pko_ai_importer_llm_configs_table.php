<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pko_ai_importer_llm_configs') && ! Schema::hasTable('pko_llm_configs')) {
            Schema::rename('pko_ai_importer_llm_configs', 'pko_llm_configs');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pko_llm_configs') && ! Schema::hasTable('pko_ai_importer_llm_configs')) {
            Schema::rename('pko_llm_configs', 'pko_ai_importer_llm_configs');
        }
    }
};

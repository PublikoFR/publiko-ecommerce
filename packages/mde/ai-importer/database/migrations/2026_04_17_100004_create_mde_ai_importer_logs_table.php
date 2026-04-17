<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mde_ai_importer_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('import_job_id')
                ->constrained('mde_ai_importer_jobs')
                ->cascadeOnDelete();

            $table->unsignedInteger('row_number')->nullable();
            $table->string('level', 16);
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('import_job_id');
            $table->index(['import_job_id', 'level'], 'mde_logs_job_level_idx');
            $table->index(['import_job_id', 'row_number'], 'mde_logs_job_row_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mde_ai_importer_logs');
    }
};

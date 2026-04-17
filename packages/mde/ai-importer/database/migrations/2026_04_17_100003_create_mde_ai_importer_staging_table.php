<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('lunar.database.table_prefix', 'lunar_');

        Schema::create('mde_ai_importer_staging', function (Blueprint $table) use ($prefix): void {
            $table->id();

            $table->foreignId('import_job_id')
                ->constrained('mde_ai_importer_jobs')
                ->cascadeOnDelete();

            $table->unsignedInteger('row_number');

            $table->longText('data'); // JSON (LONGTEXT pour gros payloads)

            $table->string('status', 16)->default('pending');
            $table->text('error_message')->nullable();

            $table->foreignId('lunar_product_id')
                ->nullable()
                ->constrained($prefix.'products')
                ->nullOnDelete();

            $table->dateTime('validated_at')->nullable();
            $table->dateTime('imported_at')->nullable();

            $table->timestamps();

            $table->index(['import_job_id', 'status'], 'mde_staging_job_status_idx');
            $table->index(['import_job_id', 'row_number'], 'mde_staging_job_row_idx');
            $table->index('lunar_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mde_ai_importer_staging');
    }
};

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

        Schema::create('mde_ai_importer_jobs', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('config_id')
                ->nullable()
                ->constrained('mde_ai_importer_configs')
                ->nullOnDelete();

            $table->string('input_file_path', 500);
            $table->string('output_file_path', 500)->nullable();

            $table->string('status', 32)->default('pending');
            $table->string('import_status', 32)->default('pending');

            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('chunk_size')->default(500);
            $table->unsignedInteger('row_limit')->nullable();

            $table->json('options')->nullable();

            $table->unsignedInteger('staging_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);

            $table->dateTime('scheduled_at')->nullable();
            $table->string('error_policy', 32)->default('ignore');

            $table->unsignedInteger('last_processed_row')->nullable();
            $table->unsignedInteger('error_count')->default(0);

            $table->boolean('can_resume')->default(true);
            $table->boolean('stopped_by_user')->default(false);
            $table->boolean('rollback_completed')->default(false);

            $table->dateTime('parse_started_at')->nullable();
            $table->dateTime('parse_completed_at')->nullable();
            $table->dateTime('import_started_at')->nullable();
            $table->dateTime('import_completed_at')->nullable();

            $table->string('backup_path', 500)->nullable();
            $table->text('error_message')->nullable();

            $table->string('queue_job_id', 64)->nullable();
            $table->uuid('queue_batch_id')->nullable();

            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained($prefix.'staff')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('import_status');
            $table->index('scheduled_at');
            $table->index('config_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mde_ai_importer_jobs');
    }
};

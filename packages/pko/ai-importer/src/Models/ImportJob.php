<?php

declare(strict_types=1);

namespace Pko\AiImporter\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Lunar\Admin\Models\Staff;
use Pko\AiImporter\Enums\ErrorPolicy;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $config_id
 * @property string $input_file_path
 * @property string|null $output_file_path
 * @property JobStatus $status
 * @property ImportStatus $import_status
 * @property int|null $total_rows
 * @property int $processed_rows
 * @property int $chunk_size
 * @property int|null $row_limit
 * @property \ArrayObject|null $options
 * @property int $staging_count
 * @property int $imported_count
 * @property Carbon|null $scheduled_at
 * @property ErrorPolicy $error_policy
 * @property int|null $last_processed_row
 * @property int $error_count
 * @property bool $can_resume
 * @property bool $stopped_by_user
 * @property bool $rollback_completed
 * @property string|null $backup_path
 * @property string|null $error_message
 */
class ImportJob extends Model
{
    use HasUuids;

    protected $table = 'pko_ai_importer_jobs';

    protected $guarded = [];

    protected $casts = [
        'status' => JobStatus::class,
        'import_status' => ImportStatus::class,
        'error_policy' => ErrorPolicy::class,
        'options' => AsArrayObject::class,
        'scheduled_at' => 'datetime',
        'parse_started_at' => 'datetime',
        'parse_completed_at' => 'datetime',
        'import_started_at' => 'datetime',
        'import_completed_at' => 'datetime',
        'can_resume' => 'boolean',
        'stopped_by_user' => 'boolean',
        'rollback_completed' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(ImporterConfig::class, 'config_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_id');
    }

    public function stagingRecords(): HasMany
    {
        return $this->hasMany(StagingRecord::class, 'import_job_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ImportLog::class, 'import_job_id');
    }

    public function progressPercentage(): int
    {
        if (! $this->total_rows || $this->total_rows < 1) {
            return 0;
        }

        return (int) min(100, round(($this->processed_rows / $this->total_rows) * 100));
    }
}

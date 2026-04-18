<?php

declare(strict_types=1);

namespace Pko\AiImporter\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pko\AiImporter\Enums\LogLevel;

/**
 * @property int $id
 * @property int $import_job_id
 * @property int|null $row_number
 * @property LogLevel $level
 * @property string $message
 * @property \ArrayObject|null $context
 */
class ImportLog extends Model
{
    protected $table = 'pko_ai_importer_logs';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'level' => LogLevel::class,
        'context' => AsArrayObject::class,
        'created_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}

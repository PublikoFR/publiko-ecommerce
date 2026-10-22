<?php

declare(strict_types=1);

namespace Pko\AiImporter\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\LogPhase;

/**
 * @property int $id
 * @property int $import_job_id
 * @property LogPhase|null $phase
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
        'phase' => LogPhase::class,
        'level' => LogLevel::class,
        'context' => AsArrayObject::class,
        'created_at' => 'datetime',
    ];

    /**
     * Ligne prête pour un insert en bloc (`DB::table()->insert()`), qui ne
     * passe ni par les casts ni par le défaut SQL de `created_at` : l'heure est
     * posée ici, dans le fuseau de l'application, comme pour un `create()`.
     *
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    public static function row(
        int $jobId,
        LogPhase $phase,
        LogLevel $level,
        string $message,
        ?int $rowNumber = null,
        ?array $context = null,
    ): array {
        return [
            'import_job_id' => $jobId,
            'phase' => $phase->value,
            'row_number' => $rowNumber,
            'level' => $level->value,
            'message' => $message,
            'context' => $context !== null && $context !== [] ? json_encode($context) : null,
            'created_at' => now(),
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}

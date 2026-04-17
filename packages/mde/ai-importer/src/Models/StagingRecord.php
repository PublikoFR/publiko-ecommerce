<?php

declare(strict_types=1);

namespace Mde\AiImporter\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Product;
use Mde\AiImporter\Enums\StagingStatus;

/**
 * @property int $id
 * @property int $import_job_id
 * @property int $row_number
 * @property \ArrayObject $data
 * @property StagingStatus $status
 * @property string|null $error_message
 * @property int|null $lunar_product_id
 */
class StagingRecord extends Model
{
    protected $table = 'mde_ai_importer_staging';

    protected $guarded = [];

    protected $casts = [
        'data' => AsArrayObject::class,
        'status' => StagingStatus::class,
        'validated_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'lunar_product_id');
    }
}

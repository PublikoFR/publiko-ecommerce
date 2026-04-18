<?php

declare(strict_types=1);

namespace Pko\AiImporter\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $supplier_name
 * @property string|null $description
 * @property \ArrayObject $config_data
 */
class ImporterConfig extends Model
{
    protected $table = 'pko_ai_importer_configs';

    protected $guarded = [];

    protected $casts = [
        'config_data' => AsArrayObject::class,
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(ImportJob::class, 'config_id');
    }
}

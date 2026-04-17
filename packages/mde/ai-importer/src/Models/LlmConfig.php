<?php

declare(strict_types=1);

namespace Mde\AiImporter\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Mde\AiImporter\Enums\LlmProviderName;

/**
 * @property int $id
 * @property string $name
 * @property LlmProviderName $provider
 * @property string $api_key (encrypted at rest)
 * @property string $model
 * @property \ArrayObject|null $options
 * @property bool $is_default
 * @property bool $active
 */
class LlmConfig extends Model
{
    protected $table = 'mde_ai_importer_llm_configs';

    protected $guarded = [];

    protected $casts = [
        'provider' => LlmProviderName::class,
        'api_key' => 'encrypted',
        'options' => AsArrayObject::class,
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    public static function default(): ?self
    {
        return static::query()
            ->where('is_default', true)
            ->where('active', true)
            ->first();
    }
}

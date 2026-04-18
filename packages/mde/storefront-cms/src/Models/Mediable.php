<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class Mediable extends MorphPivot
{
    protected $table = 'mde_mediables';

    public $incrementing = true;

    protected $casts = [
        'position' => 'integer',
    ];

    protected $fillable = [
        'media_id',
        'mediable_type',
        'mediable_id',
        'mediagroup',
        'position',
    ];
}

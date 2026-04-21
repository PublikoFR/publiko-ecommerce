<?php

declare(strict_types=1);

namespace Pko\LunarMediaCore\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class Mediable extends MorphPivot
{
    protected $table = 'pko_mediables';

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

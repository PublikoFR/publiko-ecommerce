<?php

declare(strict_types=1);

namespace Pko\Secrets\Models;

use Illuminate\Database\Eloquent\Model;

class Secret extends Model
{
    protected $table = 'pko_secrets';

    protected $fillable = ['module', 'key', 'value'];

    protected $casts = [
        'value' => 'encrypted',
    ];
}

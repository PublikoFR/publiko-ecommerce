<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'pko_suppliers';

    protected $fillable = [
        'name',
        'bl_neutre',
        'lead_time_min_days',
        'lead_time_max_days',
        'notes',
    ];

    protected $casts = [
        'bl_neutre' => 'boolean',
        'lead_time_min_days' => 'integer',
        'lead_time_max_days' => 'integer',
    ];
}

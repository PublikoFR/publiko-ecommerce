<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierGridBracket extends Model
{
    protected $table = 'pko_carrier_grids';

    protected $fillable = ['carrier_code', 'service_code', 'max_kg', 'price_cents', 'sort'];

    protected $casts = [
        'max_kg' => 'integer',
        'price_cents' => 'integer',
        'sort' => 'integer',
    ];
}

<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierService extends Model
{
    protected $table = 'pko_carrier_services';

    protected $fillable = ['carrier_code', 'service_code', 'label', 'enabled', 'sort'];

    protected $casts = [
        'enabled' => 'boolean',
        'sort' => 'integer',
    ];
}

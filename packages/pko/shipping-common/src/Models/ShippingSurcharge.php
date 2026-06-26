<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingSurcharge extends Model
{
    protected $table = 'pko_shipping_surcharges';

    protected $fillable = [
        'code',
        'label',
        'amount_cents',
        'mode',
        'rule',
        'enabled',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'rule' => 'array',
        'enabled' => 'boolean',
    ];
}

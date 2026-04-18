<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Order;

class CarrierShipment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CREATED = 'created';

    public const STATUS_FAILED = 'failed';

    protected $table = 'pko_carrier_shipments';

    protected $guarded = [];

    protected $casts = [
        'payload_sent' => AsArrayObject::class,
        'response_received' => AsArrayObject::class,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

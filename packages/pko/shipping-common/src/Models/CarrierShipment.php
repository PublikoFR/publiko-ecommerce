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

    public const DELIVERY_UNKNOWN = 'unknown';

    public const DELIVERY_IN_TRANSIT = 'in_transit';

    public const DELIVERY_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const DELIVERY_DELIVERED = 'delivered';

    public const DELIVERY_RETURNED = 'returned';

    public const DELIVERY_FAILED = 'failed';

    public const DELIVERY_TERMINAL_STATUSES = [
        self::DELIVERY_DELIVERED,
        self::DELIVERY_RETURNED,
        self::DELIVERY_FAILED,
    ];

    protected $table = 'pko_carrier_shipments';

    protected $guarded = [];

    protected $casts = [
        'payload_sent' => AsArrayObject::class,
        'response_received' => AsArrayObject::class,
        'tracking_events' => AsArrayObject::class,
        'delivery_status_updated_at' => 'datetime',
        'delivered_at' => 'datetime',
        'notified_customer_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

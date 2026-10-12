<?php

declare(strict_types=1);

namespace Pko\Pennylane\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

/**
 * Bon de livraison émis dans Pennylane pour une commande expédiée.
 *
 * @property int $id
 * @property int|null $order_id
 * @property int|null $pennylane_id
 * @property string|null $pennylane_document_number
 * @property string $external_reference
 * @property string $status
 * @property string|null $last_error
 * @property array|null $payload_snapshot
 * @property Carbon|null $synced_at
 */
final class PennylaneDeliveryNote extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_FAILED = 'failed';

    protected $table = 'pko_pennylane_delivery_notes';

    protected $guarded = [];

    protected $casts = [
        'pennylane_id' => 'integer',
        'payload_snapshot' => AsArrayObject::class,
        'synced_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function pdfFilename(): string
    {
        $number = preg_replace('/[^A-Za-z0-9._-]/', '-', $this->pennylane_document_number ?: (string) $this->id);

        return 'Bon-de-livraison-'.$number.'.pdf';
    }
}

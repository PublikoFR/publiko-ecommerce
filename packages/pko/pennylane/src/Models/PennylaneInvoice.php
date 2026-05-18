<?php

declare(strict_types=1);

namespace Pko\Pennylane\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Lunar\Models\Transaction;

/**
 * @property int $id
 * @property int|null $order_id
 * @property int|null $transaction_id
 * @property int|null $parent_invoice_id
 * @property string $type
 * @property int|null $pennylane_id
 * @property string|null $pennylane_invoice_number
 * @property string $external_reference
 * @property string $status
 * @property string|null $last_error
 * @property array|null $payload_snapshot
 * @property Carbon|null $synced_at
 */
final class PennylaneInvoice extends Model
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_FAILED = 'failed';

    protected $table = 'pko_pennylane_invoices';

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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_invoice_id');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }
}

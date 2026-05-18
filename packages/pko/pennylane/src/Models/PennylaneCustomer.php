<?php

declare(strict_types=1);

namespace Pko\Pennylane\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Customer;

/**
 * @property int $id
 * @property int $lunar_customer_id
 * @property int|null $pennylane_customer_id
 * @property string $external_reference
 * @property array|null $payload_snapshot
 * @property Carbon|null $synced_at
 */
final class PennylaneCustomer extends Model
{
    protected $table = 'pko_pennylane_customers';

    protected $guarded = [];

    protected $casts = [
        'pennylane_customer_id' => 'integer',
        'payload_snapshot' => AsArrayObject::class,
        'synced_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'lunar_customer_id');
    }
}

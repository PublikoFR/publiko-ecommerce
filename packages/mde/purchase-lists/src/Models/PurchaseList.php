<?php

declare(strict_types=1);

namespace Mde\PurchaseLists\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Models\Customer;

class PurchaseList extends Model
{
    protected $table = 'mde_purchase_lists';

    protected $fillable = ['customer_id', 'name', 'notes'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseListItem::class);
    }
}

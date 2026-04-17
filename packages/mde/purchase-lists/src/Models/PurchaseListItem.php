<?php

declare(strict_types=1);

namespace Mde\PurchaseLists\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PurchaseListItem extends Model
{
    protected $table = 'mde_purchase_list_items';

    protected $fillable = ['purchase_list_id', 'purchasable_id', 'purchasable_type', 'quantity', 'meta'];

    protected $casts = ['meta' => 'array', 'quantity' => 'integer'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(PurchaseList::class, 'purchase_list_id');
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }
}

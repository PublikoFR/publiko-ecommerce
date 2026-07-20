<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\ProductVariant;

/**
 * Prix négocié (contractuel) HT propre à un client, pour une variante produit.
 *
 * Appliqué à la résolution des prix via NegotiatedPricePipeline (registré dans
 * config/lunar/pricing.php) : le prix négocié écrase le prix Lunar s'il est plus
 * bas, mais une promotion ou un prix dégressif par quantité peut descendre en
 * dessous (on garde toujours le prix le plus avantageux pour le client).
 *
 * @property int $customer_id
 * @property int $product_variant_id
 * @property int $currency_id
 * @property int $price Prix HT en centimes
 */
class NegotiatedPrice extends Model
{
    protected $table = 'pko_negotiated_prices';

    protected $fillable = [
        'customer_id',
        'product_variant_id',
        'currency_id',
        'price',
    ];

    protected $casts = [
        'price' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}

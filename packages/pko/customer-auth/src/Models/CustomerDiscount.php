<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Customer;

/**
 * Remise client permanente (en %) sur une cible catalogue.
 *
 * Cibles : collection Lunar (couvre aussi toute sa descendance dans l'arbre),
 * famille fabricant (`pko_brand_families`) ou marque Lunar. Pas de cumul : la
 * plus forte remise couvrant un produit l'emporte, puis le prix le plus bas
 * entre prix négocié et prix remisé (cf. CustomerDiscountResolver).
 *
 * @property int $customer_id
 * @property string $target_type
 * @property int $target_id
 * @property string $percent Décimal à 2 chiffres (ex. "12.50")
 */
class CustomerDiscount extends Model
{
    public const TARGET_COLLECTION = 'collection';

    public const TARGET_BRAND_FAMILY = 'brand_family';

    public const TARGET_BRAND = 'brand';

    public const TARGET_TYPES = [
        self::TARGET_COLLECTION,
        self::TARGET_BRAND_FAMILY,
        self::TARGET_BRAND,
    ];

    protected $table = 'pko_customer_discounts';

    protected $fillable = [
        'customer_id',
        'target_type',
        'target_id',
        'percent',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'target_id' => 'integer',
        'percent' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Remise exprimée en points de base (12.50 % → 1250), pour un calcul entier. */
    public function basisPoints(): int
    {
        return self::toBasisPoints((string) $this->percent);
    }

    public static function toBasisPoints(string|int|float $percent): int
    {
        $bp = (int) round(((float) $percent) * 100);

        return max(0, min(10000, $bp));
    }
}

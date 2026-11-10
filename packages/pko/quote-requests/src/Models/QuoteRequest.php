<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Models\Customer;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Enums\QuoteRequestStatus;

/**
 * @property int $id
 * @property QuoteRequestMode $mode
 * @property QuoteRequestStatus $status
 * @property string $name
 * @property string|null $company
 * @property string $email
 * @property string|null $phone
 * @property string|null $postcode
 * @property string|null $subject
 * @property string $message
 * @property array<int, array{label: string, quantity: int}>|null $lines
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property string|null $reference
 * @property string|null $collection
 * @property string|null $from_url
 * @property array<string, mixed>|null $context
 * @property int|null $customer_id
 * @property int|null $user_id
 */
class QuoteRequest extends Model
{
    protected $table = 'pko_quote_requests';

    protected $guarded = ['id'];

    protected $casts = [
        'mode' => QuoteRequestMode::class,
        'status' => QuoteRequestStatus::class,
        'lines' => 'array',
        'context' => 'array',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected static function booted(): void
    {
        // La FK cascade en base ne déclenche pas les événements Eloquent : on
        // supprime les pièces jointes une par une pour effacer aussi le disque.
        static::deleting(function (self $request): void {
            $request->files()->get()->each->delete();
        });
    }

    /** @return HasMany<QuoteRequestFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(QuoteRequestFile::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::modelClass(), 'product_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::modelClass(), 'product_variant_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::modelClass(), 'customer_id');
    }

    /** Numéro lisible, stable (dérivé du type et de l'id) : « DEV-000042 », « MSG-000043 ». */
    public function number(): string
    {
        return $this->mode->numberPrefix().'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** Libellé du produit d'origine, figé à la création (cf. context). */
    public function productLabel(): ?string
    {
        $label = $this->context['product_name'] ?? null;

        return is_string($label) && $label !== '' ? $label : null;
    }
}

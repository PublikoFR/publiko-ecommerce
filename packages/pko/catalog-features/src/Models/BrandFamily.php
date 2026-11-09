<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Models\Brand;
use Lunar\Models\Product;

/**
 * Famille fabricant d'un produit (famille de remise FAB-DIS), propre à une marque.
 *
 * PRIVÉE : volontairement sans `#[ApiResource]`, jamais rendue côté storefront.
 * Sert de cible exacte aux remises (marque + code). Alimentée par l'import
 * (clés `brand_family_code` / `brand_family_label`), cf. docs/packages/catalog-features.md.
 *
 * @property int $id
 * @property int $brand_id
 * @property string $code
 * @property string|null $label
 */
class BrandFamily extends Model
{
    protected $table = 'pko_brand_families';

    protected $guarded = [];

    protected $casts = [
        'brand_id' => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'pko_brand_family_id');
    }

    /** Libellé d'affichage admin : « CODE — Libellé » (ou le code seul). */
    public function displayName(): string
    {
        return $this->label !== null && $this->label !== ''
            ? "{$this->code} — {$this->label}"
            : $this->code;
    }
}

<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Brand;
use Lunar\Models\Product;

/**
 * Compatibilité documentée d'une pièce (product_id) avec un équipement.
 *
 * La marque de l'équipement est soit une marque du catalogue
 * (`equipment_brand_id`), soit un libellé libre (`equipment_brand_label`).
 * Cf. docs/packages/catalog-features.md § Compatibilités.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $equipment_brand_id
 * @property string|null $equipment_brand_label
 * @property string|null $equipment_model
 * @property string|null $equipment_reference
 * @property int|null $compatible_product_id
 * @property string|null $note
 * @property int $sort_order
 */
class ProductCompatibility extends Model
{
    protected $table = 'pko_product_compatibilities';

    protected $guarded = [];

    protected $casts = [
        'product_id' => 'integer',
        'equipment_brand_id' => 'integer',
        'compatible_product_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function equipmentBrand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'equipment_brand_id');
    }

    public function compatibleProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'compatible_product_id');
    }

    /** Nom de la marque d'équipement : marque du catalogue, sinon libellé libre. */
    public function equipmentBrandName(): ?string
    {
        $name = $this->equipmentBrand?->name ?? $this->equipment_brand_label;

        return $name !== null && trim($name) !== '' ? $name : null;
    }
}

<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Brand;

/**
 * @property int $id
 * @property int $brand_id
 * @property ?string $layout
 * @property ?array $content
 * @property ?string $seo_title
 * @property ?string $seo_description
 */
class BrandPage extends Model
{
    protected $table = 'pko_brand_pages';

    protected $guarded = [];

    protected $casts = [
        'content' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public static function firstOrNewForBrand(int $brandId): self
    {
        return self::firstOrNew(['brand_id' => $brandId]);
    }
}

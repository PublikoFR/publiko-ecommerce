<?php

declare(strict_types=1);

namespace Pko\ProductVideos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Product;
use Pko\ProductVideos\Enums\VideoProvider;

/**
 * @property int $id
 * @property int $product_id
 * @property string $url
 * @property VideoProvider $provider
 * @property ?string $provider_video_id
 * @property ?string $thumbnail_url
 * @property ?string $title
 * @property int $sort_order
 */
class ProductVideo extends Model
{
    protected $table = 'pko_product_videos';

    protected $guarded = [];

    protected $casts = [
        'provider' => VideoProvider::class,
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

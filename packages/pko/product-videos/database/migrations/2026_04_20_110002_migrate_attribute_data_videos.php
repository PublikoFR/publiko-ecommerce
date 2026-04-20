<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Pko\ProductVideos\Services\ProductVideoManager;

/**
 * Data migration : transfert des URLs stockées en string CSV sur
 * `attribute_data.videos` vers la table dédiée `pko_product_videos`, puis
 * suppression de la clé. Irréversible (down = noop).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(Product::class) || ! class_exists(ProductVideoManager::class)) {
            return;
        }

        $manager = app(ProductVideoManager::class);

        Product::query()
            ->whereNotNull('attribute_data')
            ->chunkById(100, function ($products) use ($manager): void {
                foreach ($products as $product) {
                    $attrs = $product->attribute_data;
                    if ($attrs === null) {
                        continue;
                    }

                    // attribute_data is an AttributeData collection of FieldType objects.
                    $data = $attrs->all();
                    if (! isset($data['videos'])) {
                        continue;
                    }

                    $raw = $data['videos'];
                    $rawValue = is_object($raw) && method_exists($raw, 'getValue')
                        ? (string) $raw->getValue()
                        : (string) $raw;

                    $urls = array_filter(array_map('trim', explode(',', $rawValue)));
                    foreach ($urls as $url) {
                        try {
                            $manager->addIfNotExists($product, $url);
                        } catch (Throwable) {
                            // URL malformée — on la saute, pas bloquant.
                        }
                    }

                    // Retire la clé `videos` du set attribute_data.
                    unset($data['videos']);
                    DB::table((new Product)->getTable())
                        ->where('id', $product->id)
                        ->update(['attribute_data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        // Irréversible. Les données restent dans pko_product_videos.
    }
};

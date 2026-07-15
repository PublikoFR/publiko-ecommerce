<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

if (! function_exists('pko_media_url')) {
    /**
     * URL d'un média avec fallback SÛR sur l'original.
     *
     * Les médias de la médiathèque (owner = `Folder` du média-manager) n'ont pas
     * les conversions Lunar (`large`/`medium`/`small`). Retourne l'URL de la
     * conversion demandée SI elle a été générée, sinon l'original — évite le
     * `InvalidConversion: There is no conversion named 'x'` côté storefront.
     *
     * Fix temporaire (option 2) : sert l'original quand la conversion manque.
     * À terme, générer les conversions sur les médias-bibliothèque.
     */
    function pko_media_url(?Media $media, string $conversion = ''): string
    {
        if ($media === null) {
            return '';
        }

        if ($conversion !== '' && $media->hasGeneratedConversion($conversion)) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
    }
}

if (! function_exists('pko_product_thumbnail')) {
    /**
     * Résout le média « vignette » d'un produit en privilégiant la médiathèque
     * custom (`pko_mediables`, groupe `product`, première position) — la source
     * utilisée par l'éditeur produit unifié ET l'importeur. Fallback sur le média
     * natif Lunar (`$product->thumbnail`) pour les produits non migrés.
     *
     * Nécessaire car l'importeur/éditeur n'attache que du média-bibliothèque :
     * `$product->thumbnail` (natif) est null → cards sans photo sans ce résolveur.
     *
     * @param  Product|null  $product
     */
    function pko_product_thumbnail($product): ?Media
    {
        if ($product === null) {
            return null;
        }

        $mediaId = DB::table('pko_mediables')
            ->where('mediable_type', $product::class)
            ->where('mediable_id', $product->getKey())
            ->where('mediagroup', 'product')
            ->orderBy('position')
            ->value('media_id');

        if ($mediaId !== null) {
            return Media::query()->find((int) $mediaId);
        }

        return $product->thumbnail ?? null;
    }
}

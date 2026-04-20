<?php

declare(strict_types=1);

namespace Pko\ProductDocuments\Services;

use Lunar\Models\Product;
use Pko\ProductDocuments\Models\ProductDocument;

class ProductDocumentManager
{
    /**
     * Attache un média à un produit dans une catégorie donnée.
     * Met à jour category_id et sort_order si le lien existe déjà.
     */
    public function attach(Product $product, int $mediaId, ?int $categoryId = null, int $sortOrder = 0): ProductDocument
    {
        /** @var ProductDocument $doc */
        $doc = ProductDocument::updateOrCreate(
            ['product_id' => $product->id, 'media_id' => $mediaId],
            ['category_id' => $categoryId, 'sort_order' => $sortOrder],
        );

        return $doc;
    }

    /**
     * Retire un lien document-produit (par media_id).
     */
    public function detach(Product $product, int $mediaId): void
    {
        ProductDocument::where('product_id', $product->id)
            ->where('media_id', $mediaId)
            ->delete();
    }

    /**
     * Synchronise la liste complète des documents d'un produit.
     * Les entrées absentes de $rows sont supprimées.
     *
     * @param  array<int, array{media_id:int, category_id:int|null}>  $rows
     */
    public function sync(Product $product, array $rows): void
    {
        $incoming = collect($rows)
            ->filter(fn (array $r) => (int) ($r['media_id'] ?? 0) > 0)
            ->values();

        $incomingMediaIds = $incoming->pluck('media_id')->map(fn ($v) => (int) $v)->all();

        ProductDocument::where('product_id', $product->id)
            ->whereNotIn('media_id', $incomingMediaIds)
            ->delete();

        foreach ($incoming as $position => $row) {
            $this->attach(
                $product,
                (int) $row['media_id'],
                isset($row['category_id']) ? ((int) $row['category_id'] ?: null) : null,
                $position,
            );
        }
    }
}

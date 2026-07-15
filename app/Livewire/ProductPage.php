<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Traits\FetchesUrls;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\ProductDocuments\Models\ProductDocument;
use Pko\ShippingCommon\Models\Supplier;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductPage extends Component
{
    use FetchesUrls;

    /**
     * The selected option values.
     */
    public array $selectedOptionValues = [];

    public function mount($slug): void
    {
        $this->url = $this->fetchUrl(
            $slug,
            (new Product)->getMorphClass(),
            [
                'element.media',
                'element.variants.basePrices.currency',
                'element.variants.basePrices.priceable',
                'element.variants.values.option',
            ]
        );

        if (! $this->url) {
            abort(404);
        }

        // Abort if all of the product's collections are disabled.
        $hasVisibleCollection = $this->url->element->collections()
            ->navVisible()
            ->exists();

        if (! $hasVisibleCollection) {
            abort(404);
        }

        $this->selectedOptionValues = $this->productOptions->mapWithKeys(function ($data) {
            return [$data['option']->id => $data['values']->first()->id];
        })->toArray();
    }

    /**
     * Computed property to get variant.
     */
    public function getVariantProperty(): ProductVariant
    {
        return $this->product->variants->first(function ($variant) {
            return ! $variant->values->pluck('id')
                ->diff(
                    collect($this->selectedOptionValues)->values()
                )->count();
        });
    }

    /**
     * Computed property to return all available option values.
     */
    public function getProductOptionValuesProperty(): Collection
    {
        return $this->product->variants->pluck('values')->flatten();
    }

    /**
     * Computed propert to get available product options with values.
     */
    public function getProductOptionsProperty(): Collection
    {
        return $this->productOptionValues->unique('id')->groupBy('product_option_id')
            ->map(function ($values) {
                return [
                    'option' => $values->first()->option,
                    'values' => $values,
                ];
            })->values();
    }

    /**
     * Computed property to return product.
     */
    public function getProductProperty(): Product
    {
        return $this->url->element;
    }

    /**
     * Return all images for the product.
     *
     * Source unifiée avec l'admin : la médiathèque custom (`pko_mediables`,
     * groupe `product`). Fallback rétro-compat sur les médias Spatie natifs du
     * produit pour les anciennes galeries non encore migrées.
     */
    public function getImagesProperty(): Collection
    {
        $mediaIds = DB::table('pko_mediables')
            ->where('mediable_type', $this->product::class)
            ->where('mediable_id', $this->product->id)
            ->where('mediagroup', 'product')
            ->orderBy('position')
            ->pluck('media_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($mediaIds === []) {
            return $this->product->media->sortBy('order_column');
        }

        $byId = Media::query()->whereIn('id', $mediaIds)->get()->keyBy('id');

        return collect($mediaIds)
            ->map(fn (int $id): ?Media => $byId->get($id))
            ->filter()
            ->values();
    }

    /**
     * Computed property to return current image.
     */
    public function getImageProperty(): ?Media
    {
        if (count($this->variant->images)) {
            return $this->variant->images->first();
        }

        if ($primary = $this->images->first(fn ($media) => $media->getCustomProperty('primary'))) {
            return $primary;
        }

        return $this->images->first();
    }

    /**
     * Documents téléchargeables groupés par catégorie — réservés aux clients connectés.
     */
    /**
     * Fournisseur externe lié au produit courant, ou null si stock Weklo.
     */
    public function getSupplierProperty(): ?Supplier
    {
        $supplierId = $this->product->pko_supplier_id;

        return $supplierId !== null ? Supplier::find($supplierId) : null;
    }

    public function getDocumentsProperty(): Collection
    {
        if (! auth()->check()) {
            return collect();
        }

        return ProductDocument::with(['media', 'category'])
            ->where('product_id', $this->product->id)
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (ProductDocument $d): string => $d->category?->label ?? 'Documents');
    }

    public function render(): View
    {
        return view('livewire.product-page');
    }
}

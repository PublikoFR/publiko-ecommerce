<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Traits\FetchesUrls;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\CatalogFeatures\Models\ProductCompatibility;
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
                'element.videos',
                'element.variants.basePrices.currency',
                'element.variants.basePrices.priceable',
                'element.variants.values.option',
            ]
        );

        if (! $this->url) {
            abort(404);
        }

        // Un produit sans aucune collection reste visible (traité comme
        // « Non classé »). On ne masque (404) que lorsqu'il possède des
        // collections mais qu'aucune n'est visible en navigation.
        $element = $this->url->element;

        if ($element->collections()->exists()
            && ! $element->collections()->navVisible()->exists()) {
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
     * Logo du fabricant (marque du produit), null si absent → repli texte.
     */
    public function getBrandLogoProperty(): ?Media
    {
        return pko_brand_logo($this->product->brand);
    }

    /**
     * URL de la page publique de la marque (`/marque/{slug}`), null sans marque
     * ou si la route n'est pas enregistrée. Slug : URL Lunar par défaut de la
     * marque, sinon slug du nom (même résolution que BrandController::show).
     */
    public function getBrandUrlProperty(): ?string
    {
        $brand = $this->product->brand;

        if ($brand === null || ! Route::has('brand.view')) {
            return null;
        }

        $slug = $brand->defaultUrl?->slug ?: Str::slug((string) $brand->name);

        return $slug !== '' ? route('brand.view', ['slug' => $slug]) : null;
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

    /**
     * Produits associés (cross-sell) définis dans le back-office, affichés en
     * bas de fiche produit dans la section « Ça peut aussi vous intéresser ».
     */
    public function getRelatedProductsProperty(): Collection
    {
        return $this->product->associations()
            ->where('type', 'cross-sell')
            ->with([
                'target.defaultUrl',
                'target.brand',
                'target.variants',
            ])
            ->get()
            ->pluck('target')
            ->filter()
            ->values();
    }

    /**
     * Vidéos produit (YouTube, Vimeo, Dailymotion, MP4) triées par sort_order.
     */
    public function getVideosProperty(): Collection
    {
        return $this->product->videos;
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

    /**
     * Compatibilités documentées de la pièce avec des équipements (marque,
     * modèle, référence, lien éventuel vers un produit du catalogue). Le lien
     * n'est exposé que si le produit compatible est publié et a une URL.
     *
     * @return Collection<int, ProductCompatibility>
     */
    public function getCompatibilitiesProperty(): Collection
    {
        return ProductCompatibility::query()
            ->with(['equipmentBrand', 'compatibleProduct.defaultUrl'])
            ->where('product_id', $this->product->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** URL publique du produit compatible, ou null s'il n'est pas publié. */
    public function compatibleProductUrl(ProductCompatibility $compatibility): ?string
    {
        $target = $compatibility->compatibleProduct;
        if ($target === null || $target->status !== 'published' || $target->defaultUrl === null) {
            return null;
        }

        return Route::has('product.view')
            ? route('product.view', ['slug' => $target->defaultUrl->slug])
            : null;
    }

    public function render(): View
    {
        return view('livewire.product-page');
    }
}

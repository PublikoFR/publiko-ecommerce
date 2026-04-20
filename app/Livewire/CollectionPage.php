<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Traits\FetchesUrls;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Lunar\Models\Brand;
use Lunar\Models\Collection as CollectionModel;
use Lunar\Models\Product;
use Pko\CatalogFeatures\Facades\Features;
use Pko\CatalogFeatures\Models\FeatureFamily;

class CollectionPage extends Component
{
    use FetchesUrls;
    use WithPagination;

    /** @var array<int, array<int, bool>> */
    #[Url(as: 'f')]
    public array $selected = [];

    /** @var array<int, bool> */
    #[Url(as: 'b')]
    public array $selectedBrands = [];

    #[Url(as: 'sort')]
    public string $sort = 'new';

    public function mount(string $slug): void
    {
        $this->url = $this->fetchUrl(
            $slug,
            (new CollectionModel)->getMorphClass(),
            ['element.thumbnail'],
        );

        if (! $this->url) {
            abort(404);
        }
    }

    public function toggleValue(int $familyId, int $valueId): void
    {
        $this->selected[$familyId] ??= [];
        if (! empty($this->selected[$familyId][$valueId])) {
            unset($this->selected[$familyId][$valueId]);
        } else {
            $this->selected[$familyId][$valueId] = true;
        }
        if ($this->selected[$familyId] === []) {
            unset($this->selected[$familyId]);
        }
        $this->resetPage();
    }

    public function toggleBrand(int $brandId): void
    {
        if (! empty($this->selectedBrands[$brandId])) {
            unset($this->selectedBrands[$brandId]);
        } else {
            $this->selectedBrands[$brandId] = true;
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->selected = [];
        $this->selectedBrands = [];
        $this->resetPage();
    }

    public function getCollectionProperty(): CollectionModel
    {
        return $this->url->element;
    }

    /** @return array<int> flat selected brand ids */
    private function selectedBrandIds(): array
    {
        return array_values(array_map('intval', array_keys(array_filter($this->selectedBrands))));
    }

    /**
     * Query de base : produits de la collection, sans aucun filtre feature/brand.
     *
     * @return Builder<Product>
     */
    private function baseQuery(): Builder
    {
        return Product::query()
            ->whereHas('collections', fn ($q) => $q->where('lunar_collections.id', $this->collection->id));
    }

    /**
     * Applique le filtre feature AND-logic sur une query produit.
     */
    private function applyFeatureFilters(Builder $query, ?int $excludeFamilyId = null): Builder
    {
        foreach ($this->selected as $familyId => $values) {
            if ($excludeFamilyId !== null && (int) $familyId === $excludeFamilyId) {
                continue;
            }
            $valueIds = array_values(array_filter(array_map('intval', array_keys(array_filter($values)))));
            if ($valueIds === []) {
                continue;
            }
            $expected = count($valueIds);
            $query->whereIn('lunar_products.id', function ($sub) use ($valueIds, $expected): void {
                $sub->from('pko_feature_value_product')
                    ->select('product_id')
                    ->whereIn('feature_value_id', $valueIds)
                    ->groupBy('product_id')
                    ->havingRaw('COUNT(DISTINCT feature_value_id) = ?', [$expected]);
            });
        }

        return $query;
    }

    public function getProductsProperty(): LengthAwarePaginator
    {
        $query = $this->baseQuery()
            ->with(['thumbnail', 'brand', 'defaultUrl', 'variants.basePrices']);

        $this->applyFeatureFilters($query);

        $brandIds = $this->selectedBrandIds();
        if ($brandIds !== []) {
            $query->whereIn('brand_id', $brandIds);
        }

        match ($this->sort) {
            'price-asc', 'price-desc', 'name-asc' => $query->orderBy('id'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate(24);
    }

    /** @return Collection<int, FeatureFamily> */
    public function getFamiliesProperty(): Collection
    {
        // Baseline counts (toutes familles visibles sur le scope collection)
        $collection = $this->collection;
        $familyIdsInCollection = array_keys(Features::countsFor($collection));
        if ($familyIdsInCollection === []) {
            return collect();
        }

        // Pour chaque famille, recount en excluant SES propres filtres (pattern PrestaShop).
        // Base query inclut les filtres brand actifs.
        $baseForFeatures = $this->baseQuery();
        $brandIds = $this->selectedBrandIds();
        if ($brandIds !== []) {
            $baseForFeatures->whereIn('brand_id', $brandIds);
        }

        return FeatureFamily::query()
            ->with('values')
            ->whereIn('id', $familyIdsInCollection)
            ->orderBy('position')
            ->get()
            ->map(function (FeatureFamily $family) use ($baseForFeatures) {
                $counts = Features::countsForContext(
                    (clone $baseForFeatures),
                    $this->selected,
                    (int) $family->id,
                );
                $family->value_counts = $counts;

                return $family;
            });
    }

    /** @return Collection<int, object> */
    public function getBrandsProperty(): Collection
    {
        // Base pour le recount brands : collection + filtres features appliqués
        $baseForBrands = $this->baseQuery();
        $this->applyFeatureFilters($baseForBrands);

        $counts = Features::brandCountsForContext($baseForBrands);

        if ($counts === []) {
            return collect();
        }

        return Brand::query()
            ->whereIn('id', array_keys($counts))
            ->orderBy('name')
            ->get()
            ->map(function (Brand $brand) use ($counts) {
                $brand->products_count = $counts[$brand->id] ?? 0;

                return $brand;
            });
    }

    public function render(): View
    {
        return view('livewire.collection-page', [
            'products' => $this->products,
            'families' => $this->families,
            'brands' => $this->brands,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Pko\CatalogFeatures\Facades\Features;
use Pko\CatalogFeatures\Models\FeatureFamily;

/**
 * Page de recherche storefront avec facettes réactives (features + brands).
 * Le recherche textuelle est faite par LIKE sur lunar_product_translations.name
 * et variants.sku — compatible avec le moteur Scout si activé, mais n'en dépend pas.
 */
class SearchPage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $term = null;

    /** @var array<int, array<int, bool>> */
    #[Url(as: 'f')]
    public array $selected = [];

    /** @var array<int, bool> */
    #[Url(as: 'b')]
    public array $selectedBrands = [];

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

    private function selectedBrandIds(): array
    {
        return array_values(array_map('intval', array_keys(array_filter($this->selectedBrands))));
    }

    /**
     * Base : produits matchant le terme de recherche (name/SKU/ean LIKE).
     *
     * @return Builder<Product>
     */
    private function baseQuery(): Builder
    {
        $q = Product::query();
        $term = trim((string) $this->term);

        if ($term !== '' && strlen($term) >= 2) {
            $like = '%'.addcslashes($term, '%_').'%';
            $q->where(function ($qq) use ($like): void {
                $qq->whereExists(function ($sub) use ($like): void {
                    $sub->from('lunar_product_translations as t')
                        ->whereColumn('t.product_id', 'lunar_products.id')
                        ->where('t.name', 'like', $like);
                })->orWhereHas('variants', function ($v) use ($like): void {
                    $v->where('sku', 'like', $like)
                        ->orWhere('ean', 'like', $like)
                        ->orWhere('mpn', 'like', $like);
                });
            });
        }

        return $q;
    }

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

        return $query->orderByDesc('created_at')->paginate(24);
    }

    /** @return Collection<int, FeatureFamily> */
    public function getFamiliesProperty(): Collection
    {
        $baseForFeatures = $this->baseQuery();
        $brandIds = $this->selectedBrandIds();
        if ($brandIds !== []) {
            $baseForFeatures->whereIn('brand_id', $brandIds);
        }

        // Familles présentes dans le subset : on s'appuie sur countsForContext sans exclusion pour lister.
        $baseline = Features::countsForContext((clone $baseForFeatures), $this->selected);
        if ($baseline === []) {
            return collect();
        }

        // Trouver les familles distinctes présentes
        $familyIds = FeatureFamily::query()
            ->whereIn('id', function ($sub) use ($baseline): void {
                $sub->from('pko_feature_values')
                    ->select('feature_family_id')
                    ->whereIn('id', array_keys($baseline));
            })
            ->pluck('id')
            ->all();

        return FeatureFamily::query()
            ->with('values')
            ->whereIn('id', $familyIds)
            ->orderBy('position')
            ->get()
            ->map(function (FeatureFamily $family) use ($baseForFeatures) {
                $family->value_counts = Features::countsForContext(
                    (clone $baseForFeatures),
                    $this->selected,
                    (int) $family->id,
                );

                return $family;
            });
    }

    /** @return Collection<int, object> */
    public function getBrandsProperty(): Collection
    {
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
        return view('livewire.search-page', [
            'products' => $this->products,
            'families' => $this->families,
            'brands' => $this->brands,
        ]);
    }
}

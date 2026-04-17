<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Traits\FetchesUrls;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Lunar\Models\Collection as CollectionModel;
use Lunar\Models\Product;
use Mde\CatalogFeatures\Facades\Features;
use Mde\CatalogFeatures\Models\FeatureFamily;

class CollectionPage extends Component
{
    use FetchesUrls;
    use WithPagination;

    /** @var array<int, array<int, bool>> */
    #[Url(as: 'f')]
    public array $selected = [];

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

    public function clearFilters(): void
    {
        $this->selected = [];
        $this->resetPage();
    }

    public function getCollectionProperty(): CollectionModel
    {
        return $this->url->element;
    }

    /** @return array<int, int> flat selected value ids */
    public function getSelectedValueIdsProperty(): array
    {
        $ids = [];
        foreach ($this->selected as $family => $values) {
            foreach (array_keys($values) as $valueId) {
                $ids[] = (int) $valueId;
            }
        }

        return array_values(array_unique($ids));
    }

    public function getProductsProperty(): LengthAwarePaginator
    {
        $collection = $this->collection;

        $query = Product::query()
            ->whereHas('collections', fn ($q) => $q->where('lunar_collections.id', $collection->id))
            ->with(['thumbnail', 'brand', 'defaultUrl', 'variants.basePrices']);

        $values = $this->selectedValueIds;
        if ($values !== []) {
            $filteredIds = Features::productsWith($values)->pluck('products.id');
            $query->whereIn('products.id', $filteredIds);
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
        $collection = $this->collection;
        $counts = Features::countsFor($collection);
        $familyIds = array_keys($counts);
        if ($familyIds === []) {
            return collect();
        }

        return FeatureFamily::query()
            ->with('values')
            ->whereIn('id', $familyIds)
            ->orderBy('position')
            ->get()
            ->map(function (FeatureFamily $family) use ($counts) {
                $family->value_counts = $counts[$family->id] ?? [];

                return $family;
            });
    }

    public function render(): View
    {
        return view('livewire.collection-page', [
            'products' => $this->products,
            'families' => $this->families,
        ]);
    }
}

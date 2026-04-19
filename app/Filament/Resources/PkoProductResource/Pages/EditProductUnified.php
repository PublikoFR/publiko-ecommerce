<?php

declare(strict_types=1);

namespace App\Filament\Resources\PkoProductResource\Pages;

use App\Filament\Resources\PkoProductResource;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use FilamentTiptapEditor\Enums\TiptapOutput;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use Lunar\FieldTypes\Text as FieldText;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Brand;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\AiFilament\Actions\GenerateAiAction;
use Pko\CatalogFeatures\Models\FeatureFamily;
use Pko\CatalogFeatures\Services\FeatureManager;
use Pko\StorefrontCms\Filament\Forms\Components\MediaPicker;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Page d'édition produit unifiée 2 colonnes.
 * Remplace l'éclatement en sous-pages Lunar par un unique formulaire rendu via
 * un état Livewire plat. Les médias passent par un mini Filament Form dédié
 * (composant MediaPicker Pko) ; tout le reste est piloté par des props Livewire.
 */
class EditProductUnified extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static string $resource = PkoProductResource::class;

    protected static string $view = 'filament.resources.pko-product.edit-unified';

    protected ?string $maxContentWidth = 'full';

    /** @var Product */
    public $record;

    // ------- Général
    public string $productName = '';

    public string $sku = '';

    public string $ean = '';

    public string $mpn = '';

    public string $shortDesc = '';

    public string $longDesc = '';

    // ------- Prix (en unité d'affichage, conversion ×100 à la sauvegarde)
    public ?string $price = null;

    public ?string $comparePrice = null;

    public ?string $cost = null;

    public ?int $taxClassId = null;

    /** @var array<int,array{id:?int,customer_group_id:?int,min_quantity:int,price:?string}> */
    public array $tierPrices = [];

    // ------- Inventaire
    public bool $trackStock = true;

    public int $stock = 0;

    public int $lowStockThreshold = 0;

    public int $safetyStock = 0;

    public bool $allowBackorder = false;

    public string $leadTime = '';

    // ------- Expédition
    public ?string $weight = null;

    public ?string $length = null;

    public ?string $width = null;

    public ?string $height = null;

    // ------- Statut & visibilité
    public string $status = 'draft';

    public bool $featured = false;

    public ?string $publishAt = null;

    // ------- Organisation
    public array $collectionIds = [];

    public string $collectionSearch = '';

    public ?int $brandId = null;

    public array $tagInputs = [];

    public string $newTag = '';

    // ------- Features (CatalogFeatures)
    /** @var array<int,int|array<int,int>> */
    public array $featureValues = [];

    // ------- SEO
    public string $seoTitle = '';

    public string $seoDesc = '';

    public string $productSlug = '';

    public string $canonical = '';

    public string $robots = 'index,follow';

    // ------- Produits liés
    public array $relatedProductIds = [];

    public string $relatedSearch = '';

    // ------- UI state
    public bool $isDirty = false;

    public function getTitle(): string|Htmlable
    {
        return $this->productName !== '' ? $this->productName : 'Éditer le produit';
    }

    public function mount(int|string $record): void
    {
        /** @var Product $product */
        $product = Product::query()
            ->with([
                'variants.prices.customerGroup',
                'variants.prices.currency',
                'collections',
                'tags',
                'brand',
                'defaultUrl',
                'featureValues.family',
                'associations',
            ])
            ->findOrFail($record);

        $this->record = $product;

        $attrs = $product->attribute_data ?? collect();
        $this->productName = $this->readAttr($attrs, 'name');
        $this->shortDesc = $this->readAttr($attrs, 'short_description');
        $this->longDesc = $this->readAttr($attrs, 'description');
        $this->seoTitle = $this->readAttr($attrs, 'meta_title');
        $this->seoDesc = $this->readAttr($attrs, 'meta_description');

        $this->status = (string) ($product->status ?? 'draft');
        $this->featured = (bool) ($product->featured ?? false);
        $this->brandId = $product->brand_id;
        $this->collectionIds = $product->collections->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->tagInputs = $product->tags->pluck('value')->all();

        $default = $product->variants->first();
        if ($default instanceof ProductVariant) {
            $this->sku = (string) ($default->sku ?? '');
            $this->ean = (string) ($default->ean ?? '');
            $this->mpn = (string) ($default->mpn ?? '');
            $this->stock = (int) ($default->stock ?? 0);
            $this->allowBackorder = ((int) ($default->backorder ?? 0)) > 0 || $default->purchasable === 'always';
            $this->taxClassId = $default->tax_class_id;
            $this->weight = $default->weight_value !== null ? (string) $default->weight_value : null;
            $this->length = $default->length_value !== null ? (string) $default->length_value : null;
            $this->width = $default->width_value !== null ? (string) $default->width_value : null;
            $this->height = $default->height_value !== null ? (string) $default->height_value : null;

            $currency = Currency::getDefault();
            $factor = max(1, (int) ($currency?->factor ?? 100));

            $basePrices = $default->prices->where('min_quantity', '<=', 1)->where('customer_group_id', null);
            $base = $basePrices->first();
            if ($base) {
                $this->price = $this->centsToDisplay($base->price->value ?? null, $factor);
                $this->comparePrice = $this->centsToDisplay(
                    is_object($base->compare_price ?? null) ? $base->compare_price->value : $base->compare_price,
                    $factor
                );
            }

            $this->tierPrices = $default->prices
                ->filter(fn (Price $p) => ! ($p->customer_group_id === null && $p->min_quantity <= 1))
                ->map(fn (Price $p) => [
                    'id' => $p->id,
                    'customer_group_id' => $p->customer_group_id,
                    'min_quantity' => (int) $p->min_quantity,
                    'price' => $this->centsToDisplay($p->price->value ?? null, $factor),
                ])
                ->values()
                ->all();
        }

        $this->productSlug = (string) ($product->defaultUrl?->slug ?? '');

        $this->featureValues = $product->featureValues
            ->groupBy(fn ($fv) => $fv->family->id)
            ->map(fn (Collection $group) => $group->first()->family->multi_value
                ? $group->pluck('id')->map(fn ($v) => (int) $v)->all()
                : (int) $group->first()->id)
            ->all();

        $this->relatedProductIds = $product->associations
            ->pluck('product_target_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        // Filament forms : MediaPicker (médias) + RichEditor (description longue).
        $this->mediaForm->fill();
        $this->descriptionForm->fill([
            'longDesc' => $this->longDesc,
        ]);
    }

    public array $mediaData = [];

    public array $descriptionData = [];

    protected function getForms(): array
    {
        return ['mediaForm', 'descriptionForm'];
    }

    public function mediaForm(Form $form): Form
    {
        return $form
            ->schema([
                MediaPicker::make('mediaIds')
                    ->multiple()
                    ->mediagroup('product')
                    ->folder('products'),
            ])
            ->model($this->record ?? null)
            ->statePath('mediaData');
    }

    public function descriptionForm(Form $form): Form
    {
        return $form
            ->schema([
                TiptapEditor::make('longDesc')
                    ->label('')
                    ->maxContentWidth('full')
                    ->disableFloatingMenus()
                    ->tools([
                        'heading',
                        'bold', 'italic', 'underline', '|',
                        'bullet-list', 'ordered-list', 'blockquote', 'hr', '|',
                        'link', 'source',
                    ])
                    ->hintActions(GenerateAiAction::descriptionActions())
                    ->output(TiptapOutput::Html),
            ])
            ->statePath('descriptionData');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['isDirty', 'record', 'variantPage', 'collectionSearch', 'relatedSearch'], true)) {
            return;
        }

        if (str_starts_with($property, 'mediaData') || str_starts_with($property, 'descriptionData')) {
            $this->isDirty = true;

            return;
        }
        $this->isDirty = true;
    }

    // ------- Computed SEO
    public function getSeoTitleCountProperty(): int
    {
        return mb_strlen($this->seoTitle !== '' ? $this->seoTitle : $this->productName);
    }

    public function getSeoDescCountProperty(): int
    {
        return mb_strlen($this->seoDesc !== '' ? $this->seoDesc : $this->shortDesc);
    }

    public function getSeoTitleStatusProperty(): string
    {
        $c = $this->seo_title_count;

        return match (true) {
            $c > 60 => 'danger',
            $c > 55 => 'warning',
            default => 'ok',
        };
    }

    public function getSeoDescStatusProperty(): string
    {
        $c = $this->seo_desc_count;

        return match (true) {
            $c > 160 => 'danger',
            $c > 150 => 'warning',
            default => 'ok',
        };
    }

    // ------- Variants (pagination)
    public function getVariantsProperty()
    {
        return ProductVariant::query()
            ->where('product_id', $this->record->id)
            ->with(['prices' => fn ($q) => $q->whereNull('customer_group_id')->where('min_quantity', 1)])
            ->orderBy('id')
            ->paginate(10, ['*'], 'variantPage');
    }

    public function updateVariantStock(int $variantId, int $value): void
    {
        ProductVariant::where('id', $variantId)->update(['stock' => max(0, $value)]);
        Notification::make()->title('Stock variante mis à jour')->success()->send();
    }

    public function updateVariantPurchasable(int $variantId, bool $active): void
    {
        ProductVariant::where('id', $variantId)->update([
            'purchasable' => $active ? 'always' : 'never',
        ]);
        Notification::make()->title('Statut variante mis à jour')->success()->send();
    }

    // ------- Tags inline
    public function addCollection(int $id): void
    {
        if (! in_array($id, $this->collectionIds, true)) {
            $this->collectionIds[] = $id;
            $this->isDirty = true;
        }
        $this->collectionSearch = '';
    }

    public function removeCollection(int $id): void
    {
        $this->collectionIds = array_values(array_filter(
            $this->collectionIds,
            fn ($v) => (int) $v !== $id
        ));
        $this->isDirty = true;
    }

    public function getCollectionSearchResultsProperty(): Collection
    {
        if (mb_strlen($this->collectionSearch) < 1) {
            return collect();
        }

        return $this->collectionOptions
            ->whereNotIn('id', $this->collectionIds)
            ->filter(fn ($c) => stripos(
                (string) $c->translateAttribute('name'),
                $this->collectionSearch
            ) !== false)
            ->take(8)
            ->values();
    }

    public function addTag(): void
    {
        $value = trim($this->newTag);
        if ($value !== '' && ! in_array($value, $this->tagInputs, true)) {
            $this->tagInputs[] = $value;
        }
        $this->newTag = '';
        $this->isDirty = true;
    }

    public function removeTag(string $value): void
    {
        $this->tagInputs = array_values(array_filter($this->tagInputs, fn ($t) => $t !== $value));
        $this->isDirty = true;
    }

    // ------- Tier pricing
    public function addTierPrice(): void
    {
        $this->tierPrices[] = [
            'id' => null,
            'customer_group_id' => null,
            'min_quantity' => 10,
            'price' => null,
        ];
        $this->isDirty = true;
    }

    public function removeTierPrice(int $index): void
    {
        unset($this->tierPrices[$index]);
        $this->tierPrices = array_values($this->tierPrices);
        $this->isDirty = true;
    }

    // ------- Produits liés
    public function addRelatedProduct(int $id): void
    {
        if (! in_array($id, $this->relatedProductIds, true)) {
            $this->relatedProductIds[] = $id;
            $this->isDirty = true;
        }
    }

    public function removeRelatedProduct(int $id): void
    {
        $this->relatedProductIds = array_values(array_filter(
            $this->relatedProductIds,
            fn ($v) => (int) $v !== $id
        ));
        $this->isDirty = true;
    }

    public function getRelatedSearchResultsProperty(): Collection
    {
        if (mb_strlen($this->relatedSearch) < 2) {
            return collect();
        }

        return Product::query()
            ->whereKeyNot($this->record->id)
            ->whereNotIn('id', $this->relatedProductIds)
            ->limit(8)
            ->get()
            ->filter(fn (Product $p) => stripos(
                (string) $p->translateAttribute('name'),
                $this->relatedSearch
            ) !== false);
    }

    // ------- Save
    public function save(): void
    {
        $this->validate([
            'productName' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255'],
            'price' => ['required'],
            'taxClassId' => ['required', 'integer'],
        ]);

        $longDesc = (string) ($this->descriptionForm->getState()['longDesc'] ?? $this->longDesc);

        DB::transaction(function () use ($longDesc): void {
            $product = $this->record;
            $attrs = collect($product->attribute_data?->all() ?? []);

            $attrs = $this->writeAttr($attrs, 'name', $this->productName);
            $attrs = $this->writeAttr($attrs, 'short_description', $this->shortDesc);
            $attrs = $this->writeAttr($attrs, 'description', $longDesc);
            $attrs = $this->writeAttr($attrs, 'meta_title', $this->seoTitle);
            $attrs = $this->writeAttr($attrs, 'meta_description', $this->seoDesc);

            $product->attribute_data = $attrs;
            $product->brand_id = $this->brandId;
            $product->status = $this->status;
            $product->featured = $this->featured;
            $product->save();

            $product->collections()->sync($this->collectionIds);
            $product->syncTags(collect($this->tagInputs));

            $default = $product->variants->first();
            if ($default instanceof ProductVariant) {
                $default->sku = $this->sku;
                $default->ean = $this->ean ?: null;
                $default->mpn = $this->mpn ?: null;
                $default->stock = $this->trackStock ? $this->stock : $default->stock;
                $default->backorder = $this->allowBackorder ? max(1, $this->stock) : 0;
                $default->purchasable = $this->allowBackorder ? 'always' : 'when_in_stock';
                $default->tax_class_id = $this->taxClassId;
                $default->weight_value = $this->weight !== null && $this->weight !== '' ? (float) $this->weight : null;
                $default->length_value = $this->length !== null && $this->length !== '' ? (float) $this->length : null;
                $default->width_value = $this->width !== null && $this->width !== '' ? (float) $this->width : null;
                $default->height_value = $this->height !== null && $this->height !== '' ? (float) $this->height : null;
                $default->save();

                $this->persistPrices($default);
            }

            app(FeatureManager::class)->sync($product, $this->collectFeatureValueIds());

            $product->associations()->delete();
            foreach ($this->relatedProductIds as $targetId) {
                $product->associations()->create([
                    'product_target_id' => $targetId,
                    'type' => 'cross-sell',
                ]);
            }
        });

        // Médias (mini form Filament) : persist pivots pko_mediables.
        $this->mediaForm->getState();
        $this->mediaForm->saveRelationships();

        // Associations images de description ↔ produit (pko_mediables, mediagroup = 'product-description').
        $this->syncDescriptionImages($this->record, $longDesc);

        // Refresh la prop Livewire pour que le Blade reflète la valeur sauvée.
        $this->longDesc = $longDesc;

        $this->isDirty = false;

        Notification::make()
            ->title('Produit enregistré')
            ->success()
            ->send();
    }

    public function saveAsDraft(): void
    {
        $this->status = 'draft';
        $this->save();
    }

    public function saveAndPublish(): void
    {
        $this->status = 'published';
        $this->save();
    }

    // ------- Data sources (pour le Blade)
    public function getTaxClassOptionsProperty(): Collection
    {
        return TaxClass::orderBy('name')->get(['id', 'name']);
    }

    public function getCustomerGroupOptionsProperty(): Collection
    {
        return CustomerGroup::orderBy('name')->get(['id', 'name']);
    }

    public function getCollectionOptionsProperty(): Collection
    {
        return LunarCollection::with('group')->orderBy('id')->get();
    }

    public function getBrandOptionsProperty(): Collection
    {
        return Brand::orderBy('name')->get(['id', 'name']);
    }

    public function getFeatureFamiliesProperty(): Collection
    {
        return FeatureFamily::with(['values' => fn ($q) => $q->orderBy('position')])
            ->orderBy('position')
            ->get();
    }

    public function getRelatedProductsProperty(): Collection
    {
        if (empty($this->relatedProductIds)) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $this->relatedProductIds)
            ->with('variants:id,product_id,sku')
            ->get();
    }

    public function getHistoryProperty(): Collection
    {
        return Activity::query()
            ->where('subject_type', $this->record::class)
            ->where('subject_id', $this->record->id)
            ->latest()
            ->limit(5)
            ->get();
    }

    public function getStorefrontUrlProperty(): ?string
    {
        $slug = $this->record->defaultUrl?->slug;
        if (! $slug) {
            return null;
        }

        try {
            return route('product.view', ['slug' => $slug]);
        } catch (\Throwable) {
            return null;
        }
    }

    // ------- Helpers internes
    private function readAttr(Collection $attrs, string $key): string
    {
        $value = $attrs->get($key);
        if ($value instanceof TranslatedText) {
            $values = $value->getValue() ?? [];

            return (string) (reset($values) ?: '');
        }
        if ($value instanceof FieldText) {
            return (string) $value->getValue();
        }
        if (is_array($value)) {
            return (string) (reset($value) ?: '');
        }

        return (string) ($value ?? '');
    }

    private function writeAttr(Collection $attrs, string $key, string $value): Collection
    {
        $existing = $attrs->get($key);
        if ($existing instanceof TranslatedText) {
            $locale = app()->getLocale();
            $current = $existing->getValue() ?? [];
            $current[$locale] = $value;
            $attrs->put($key, new TranslatedText($current));
        } else {
            $attrs->put($key, new FieldText($value));
        }

        return $attrs;
    }

    private function centsToDisplay(?int $cents, int $factor): ?string
    {
        if ($cents === null) {
            return null;
        }

        return number_format($cents / $factor, 2, '.', '');
    }

    private function displayToCents(?string $value, int $factor): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(((float) $value) * $factor);
    }

    private function persistPrices(ProductVariant $variant): void
    {
        $currency = Currency::getDefault();
        if (! $currency) {
            return;
        }
        $factor = max(1, (int) $currency->factor);

        // Prix de base
        $basePrice = $this->displayToCents($this->price, $factor);
        if ($basePrice !== null) {
            Price::updateOrCreate(
                [
                    'priceable_type' => $variant::class,
                    'priceable_id' => $variant->id,
                    'currency_id' => $currency->id,
                    'customer_group_id' => null,
                    'min_quantity' => 1,
                ],
                [
                    'price' => $basePrice,
                    'compare_price' => $this->displayToCents($this->comparePrice, $factor),
                ]
            );
        }

        // Paliers B2B : supprimer ceux retirés, upsert les restants.
        $keepIds = collect($this->tierPrices)->pluck('id')->filter()->all();
        Price::query()
            ->where('priceable_type', $variant::class)
            ->where('priceable_id', $variant->id)
            ->where(function ($q) {
                $q->whereNotNull('customer_group_id')
                    ->orWhere('min_quantity', '>', 1);
            })
            ->when(! empty($keepIds), fn ($q) => $q->whereNotIn('id', $keepIds))
            ->delete();

        foreach ($this->tierPrices as $tier) {
            $cents = $this->displayToCents($tier['price'] ?? null, $factor);
            if ($cents === null) {
                continue;
            }

            $attributes = [
                'currency_id' => $currency->id,
                'customer_group_id' => $tier['customer_group_id'] ?: null,
                'min_quantity' => max(1, (int) ($tier['min_quantity'] ?? 1)),
                'price' => $cents,
            ];

            if (! empty($tier['id'])) {
                Price::where('id', $tier['id'])->update($attributes);
            } else {
                Price::create(array_merge($attributes, [
                    'priceable_type' => $variant::class,
                    'priceable_id' => $variant->id,
                ]));
            }
        }
    }

    /**
     * Associe les images insérées dans la description avec le produit via `pko_mediables`
     * en utilisant le mediagroup `product-description`. Chaque sauvegarde remplace
     * intégralement le set pour ce groupe (images ajoutées ou retirées de la description).
     */
    private function syncDescriptionImages(Product $product, string $html): void
    {
        $ids = [];
        if (trim($html) !== '') {
            $pattern = '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i';
            if (preg_match_all($pattern, $html, $matches)) {
                $srcs = array_unique($matches[1] ?? []);

                if (! empty($srcs)) {
                    // Sélection par fichier (plus robuste qu'une comparaison d'URL complète).
                    $fileNames = array_filter(array_map(
                        fn (string $src) => pathinfo(parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_BASENAME) ?: null,
                        $srcs
                    ));

                    if (! empty($fileNames)) {
                        $ids = Media::query()
                            ->whereIn('file_name', $fileNames)
                            ->pluck('id')
                            ->map(fn ($v) => (int) $v)
                            ->all();
                    }
                }
            }
        }

        $group = 'product-description';
        $type = $product::class;

        DB::table('pko_mediables')
            ->where('mediable_type', $type)
            ->where('mediable_id', $product->id)
            ->where('mediagroup', $group)
            ->delete();

        if (empty($ids)) {
            return;
        }

        $now = now();
        $rows = [];
        foreach (array_values(array_unique($ids)) as $position => $mediaId) {
            $rows[] = [
                'media_id' => $mediaId,
                'mediable_type' => $type,
                'mediable_id' => $product->id,
                'mediagroup' => $group,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('pko_mediables')->insertOrIgnore($rows);
    }

    /** @return array<int,int> */
    private function collectFeatureValueIds(): array
    {
        $ids = [];
        foreach ($this->featureValues as $value) {
            if (is_array($value)) {
                foreach ($value as $id) {
                    if ($id) {
                        $ids[] = (int) $id;
                    }
                }
            } elseif ($value) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}

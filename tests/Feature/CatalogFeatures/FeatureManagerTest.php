<?php

declare(strict_types=1);

namespace Tests\Feature\CatalogFeatures;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Mde\CatalogFeatures\Events\FeatureValueAttached;
use Mde\CatalogFeatures\Events\FeatureValueDetached;
use Mde\CatalogFeatures\Events\ProductFeaturesSynced;
use Mde\CatalogFeatures\Facades\Features;
use Mde\CatalogFeatures\Models\FeatureFamily;
use Mde\CatalogFeatures\Models\FeatureValue;
use Tests\TestCase;

class FeatureManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_attach_persists_pivot_and_fires_event(): void
    {
        Event::fake([FeatureValueAttached::class]);

        [$product, $value] = $this->makeProductAndValue();

        Features::attach($product, $value);

        $this->assertDatabaseHas('mde_feature_value_product', [
            'product_id' => $product->id,
            'feature_value_id' => $value->id,
        ]);

        Event::assertDispatched(
            FeatureValueAttached::class,
            fn (FeatureValueAttached $e) => $e->product->is($product) && $e->value->is($value),
        );
    }

    public function test_detach_removes_pivot_and_fires_event(): void
    {
        Event::fake([FeatureValueDetached::class]);

        [$product, $value] = $this->makeProductAndValue();
        $product->featureValues()->attach($value->id);

        Features::detach($product, $value);

        $this->assertDatabaseMissing('mde_feature_value_product', [
            'product_id' => $product->id,
            'feature_value_id' => $value->id,
        ]);

        Event::assertDispatched(FeatureValueDetached::class);
    }

    public function test_sync_returns_diffs_and_fires_sync_event(): void
    {
        Event::fake([ProductFeaturesSynced::class]);

        $product = Product::first();
        $family = FeatureFamily::create(['handle' => 'fam', 'name' => 'Fam']);
        $v1 = FeatureValue::create(['feature_family_id' => $family->id, 'handle' => 'v1', 'name' => 'V1']);
        $v2 = FeatureValue::create(['feature_family_id' => $family->id, 'handle' => 'v2', 'name' => 'V2']);
        $v3 = FeatureValue::create(['feature_family_id' => $family->id, 'handle' => 'v3', 'name' => 'V3']);

        $product->featureValues()->attach([$v1->id, $v2->id]);

        $result = Features::sync($product, [$v2->id, $v3->id]);

        $this->assertEqualsCanonicalizing([$v3->id], $result['attached']);
        $this->assertEqualsCanonicalizing([$v1->id], $result['detached']);

        Event::assertDispatched(
            ProductFeaturesSynced::class,
            fn (ProductFeaturesSynced $e) => $e->product->is($product),
        );
    }

    public function test_sync_by_handles_preserves_unlisted_family_attachments(): void
    {
        $product = Product::first();

        $marque = FeatureFamily::create(['handle' => 'marque', 'name' => 'Marque']);
        $apps = FeatureFamily::create(['handle' => 'applications', 'name' => 'Apps']);

        $bosch = FeatureValue::create(['feature_family_id' => $marque->id, 'handle' => 'bosch', 'name' => 'Bosch']);
        FeatureValue::create(['feature_family_id' => $marque->id, 'handle' => 'makita', 'name' => 'Makita']);
        $interieur = FeatureValue::create(['feature_family_id' => $apps->id, 'handle' => 'interieur', 'name' => 'Int']);

        $product->featureValues()->attach([$bosch->id]);

        Features::syncByHandles($product, [
            'applications' => ['interieur'],
        ]);

        $ids = $product->featureValues()->pluck('mde_feature_values.id')->all();
        $this->assertContains($bosch->id, $ids, 'Brand attachment must survive an apps-only sync.');
        $this->assertContains($interieur->id, $ids);
    }

    public function test_families_for_returns_globals_plus_collection_bound(): void
    {
        $product = Product::first();
        $collectionId = $product->collections()->first()?->id;
        $this->assertNotNull($collectionId, 'Fixture product must have at least one collection.');

        $global = FeatureFamily::create(['handle' => 'glob', 'name' => 'Global']);
        $bound = FeatureFamily::create(['handle' => 'bound', 'name' => 'Bound']);
        $offTopic = FeatureFamily::create(['handle' => 'off', 'name' => 'Off']);

        $bound->collections()->attach($collectionId);
        $otherCollection = LunarCollection::query()->where('id', '!=', $collectionId)->firstOrFail();
        $offTopic->collections()->attach($otherCollection->id);

        $handles = Features::familiesFor($product)->pluck('handle')->all();

        $this->assertContains('glob', $handles);
        $this->assertContains('bound', $handles);
        $this->assertNotContains('off', $handles);
    }

    public function test_products_with_filters_on_all_requested_values(): void
    {
        $family = FeatureFamily::create(['handle' => 'fam', 'name' => 'Fam']);
        $v1 = FeatureValue::create(['feature_family_id' => $family->id, 'handle' => 'v1', 'name' => 'V1']);
        $v2 = FeatureValue::create(['feature_family_id' => $family->id, 'handle' => 'v2', 'name' => 'V2']);

        $products = Product::query()->take(3)->get();
        $this->assertCount(3, $products);

        $products[0]->featureValues()->attach([$v1->id, $v2->id]);
        $products[1]->featureValues()->attach([$v1->id]);
        $products[2]->featureValues()->attach([$v2->id]);

        $matches = Features::productsWith([$v1->id, $v2->id])->pluck('id')->all();

        $this->assertContains($products[0]->id, $matches);
        $this->assertNotContains($products[1]->id, $matches);
        $this->assertNotContains($products[2]->id, $matches);
    }

    /**
     * @return array{0: Product, 1: FeatureValue}
     */
    private function makeProductAndValue(): array
    {
        $product = Product::first();
        $family = FeatureFamily::create(['handle' => 'fam_'.uniqid(), 'name' => 'Fam']);
        $value = FeatureValue::create([
            'feature_family_id' => $family->id,
            'handle' => 'val_'.uniqid(),
            'name' => 'Val',
        ]);

        return [$product, $value];
    }
}

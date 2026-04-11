<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogFeatures;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Collection as LunarCollection;
use Mde\CatalogFeatures\Models\FeatureFamily;
use Mde\CatalogFeatures\Models\FeatureValue;
use Tests\TestCase;

class FeatureModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_has_many_values_ordered_by_position(): void
    {
        $family = FeatureFamily::create([
            'handle' => 'matiere',
            'name' => 'Matière',
        ]);

        FeatureValue::create([
            'feature_family_id' => $family->id,
            'handle' => 'bois',
            'name' => 'Bois',
            'position' => 2,
        ]);
        FeatureValue::create([
            'feature_family_id' => $family->id,
            'handle' => 'metal',
            'name' => 'Métal',
            'position' => 1,
        ]);

        $ordered = $family->values()->get()->pluck('handle')->all();
        $this->assertSame(['metal', 'bois'], $ordered);
    }

    public function test_deleting_family_cascades_to_values_and_pivots(): void
    {
        $family = FeatureFamily::create(['handle' => 'usage', 'name' => 'Usage']);
        $value = FeatureValue::create([
            'feature_family_id' => $family->id,
            'handle' => 'interieur',
            'name' => 'Intérieur',
        ]);

        $this->assertDatabaseHas('mde_feature_values', ['id' => $value->id]);

        $family->delete();

        $this->assertDatabaseMissing('mde_feature_values', ['id' => $value->id]);
    }

    public function test_value_handle_must_be_unique_per_family(): void
    {
        $family = FeatureFamily::create(['handle' => 'marque', 'name' => 'Marque']);

        FeatureValue::create([
            'feature_family_id' => $family->id,
            'handle' => 'bosch',
            'name' => 'Bosch',
        ]);

        $this->expectException(QueryException::class);

        FeatureValue::create([
            'feature_family_id' => $family->id,
            'handle' => 'bosch',
            'name' => 'Bosch duplicate',
        ]);
    }

    public function test_same_handle_across_different_families_is_allowed(): void
    {
        $a = FeatureFamily::create(['handle' => 'fam_a', 'name' => 'A']);
        $b = FeatureFamily::create(['handle' => 'fam_b', 'name' => 'B']);

        FeatureValue::create(['feature_family_id' => $a->id, 'handle' => 'shared', 'name' => 'Shared']);
        $twin = FeatureValue::create(['feature_family_id' => $b->id, 'handle' => 'shared', 'name' => 'Shared']);

        $this->assertNotNull($twin->id);
    }

    public function test_global_scope_returns_only_families_without_collections(): void
    {
        $this->seed(DatabaseSeeder::class);

        $global = FeatureFamily::create(['handle' => 'globale', 'name' => 'Globale']);
        $bound = FeatureFamily::create(['handle' => 'bornee', 'name' => 'Bornée']);

        $collection = LunarCollection::query()->first();
        $this->assertNotNull($collection, 'Seeder must provide at least one collection.');

        $bound->collections()->attach($collection->id);

        $globals = FeatureFamily::global()->pluck('handle')->all();
        $this->assertContains('globale', $globals);
        $this->assertNotContains('bornee', $globals);
    }
}

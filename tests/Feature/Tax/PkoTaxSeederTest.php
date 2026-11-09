<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use Database\Seeders\PkoTaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Country;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRateAmount;
use Tests\TestCase;

class PkoTaxSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_a_single_default_vat_20_class(): void
    {
        Country::create(['name' => 'France', 'iso3' => 'FRA', 'iso2' => 'FR', 'phonecode' => '33', 'currency' => 'EUR', 'native' => 'France', 'emoji' => 'fr', 'emoji_u' => 'U+1F1EB U+1F1F7']);
        // Ce que crée `lunar:install` avant les seeders.
        TaxClass::create(['name' => 'Default Tax Class', 'default' => true]);

        $this->seed(PkoTaxSeeder::class);
        $this->seed(PkoTaxSeeder::class);

        $classes = TaxClass::all();
        $this->assertCount(1, $classes);
        $this->assertSame('TVA 20%', $classes->first()->name);
        $this->assertTrue((bool) $classes->first()->default);
        $this->assertEquals([20.0], TaxRateAmount::query()->pluck('percentage')->map(fn ($p) => (float) $p)->all());
    }
}

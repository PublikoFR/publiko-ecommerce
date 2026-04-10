<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Country;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;

class MdeTaxSeeder extends Seeder
{
    public function run(): void
    {
        $france = Country::query()->where('iso2', 'FR')->firstOrFail();

        $taxZone = TaxZone::query()->updateOrCreate(
            ['name' => 'France métropolitaine'],
            [
                'zone_type' => 'country',
                'price_display' => 'tax_exclusive',
                'active' => true,
                'default' => true,
            ],
        );

        $taxZone->countries()->firstOrCreate(['country_id' => $france->id]);

        $standardClass = TaxClass::query()->updateOrCreate(
            ['name' => 'TVA 20%'],
            ['default' => true],
        );

        $reducedClass = TaxClass::query()->updateOrCreate(
            ['name' => 'TVA 10%'],
            ['default' => false],
        );

        $superReducedClass = TaxClass::query()->updateOrCreate(
            ['name' => 'TVA 5.5%'],
            ['default' => false],
        );

        $this->attachRate($taxZone, $standardClass, 'Standard 20%', 20);
        $this->attachRate($taxZone, $reducedClass, 'Réduit 10%', 10);
        $this->attachRate($taxZone, $superReducedClass, 'Super réduit 5.5%', 5.5);
    }

    private function attachRate(TaxZone $zone, TaxClass $class, string $label, float $percentage): void
    {
        $rate = TaxRate::query()->updateOrCreate(
            [
                'tax_zone_id' => $zone->id,
                'name' => $label,
            ],
            [
                'priority' => 1,
            ],
        );

        TaxRateAmount::query()->updateOrCreate(
            [
                'tax_rate_id' => $rate->id,
                'tax_class_id' => $class->id,
            ],
            [
                'percentage' => $percentage,
            ],
        );
    }
}

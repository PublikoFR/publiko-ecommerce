<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Country;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Lunar\Models\TaxZoneCountry;

class PkoTaxSeeder extends Seeder
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

        // `lunar:install` crée une « Default Tax Zone » contenant tous les pays,
        // sans aucun taux. Lunar retient la première zone active qui contient le
        // pays (GetTaxZoneCountry::first()) : tant que la France y reste, toutes
        // les commandes françaises sont calculées à 0 % de TVA.
        TaxZoneCountry::query()
            ->where('country_id', $france->id)
            ->where('tax_zone_id', '!=', $taxZone->id)
            ->delete();

        // Une seule classe : la boutique ne vend qu'en TVA 20 % (cf. docs/taxes.md).
        $standardClass = TaxClass::query()->updateOrCreate(
            ['name' => 'TVA 20%'],
            ['default' => true],
        );

        $this->attachRate($taxZone, $standardClass, 'Standard 20%', 20);

        // `lunar:install` a créé une « Default Tax Class » sans taux : la laisser
        // exister, c'est offrir une classe à 0 % à l'importeur et à l'admin.
        $this->removeOtherClasses($standardClass);
    }

    private function removeOtherClasses(TaxClass $keep): void
    {
        $others = TaxClass::query()->whereKeyNot($keep->id)->pluck('id');
        if ($others->isEmpty()) {
            return;
        }

        ProductVariant::query()->whereIn('tax_class_id', $others)->update(['tax_class_id' => $keep->id]);

        $rateIds = TaxRateAmount::query()->whereIn('tax_class_id', $others)->pluck('tax_rate_id');
        TaxRateAmount::query()->whereIn('tax_class_id', $others)->delete();
        TaxRate::query()->whereIn('id', $rateIds)->whereDoesntHave('taxRateAmounts')->delete();

        TaxClass::query()->whereKey($others)->delete();
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

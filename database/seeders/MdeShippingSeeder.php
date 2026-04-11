<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\DataTypes\Price as PriceValue;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;

class MdeShippingSeeder extends Seeder
{
    public function run(): void
    {
        $france = Country::query()->where('iso2', 'FR')->firstOrFail();
        $eur = Currency::query()->where('code', 'EUR')->firstOrFail();

        $zone = ShippingZone::query()->updateOrCreate(
            ['name' => 'France métropolitaine'],
            ['type' => 'country'],
        );

        $zone->countries()->syncWithoutDetaching([$france->id]);

        $standard = ShippingMethod::query()->updateOrCreate(
            ['code' => 'mde-standard'],
            [
                'name' => 'Livraison standard',
                'description' => 'Livraison en 3-5 jours ouvrables — tarif au poids',
                'enabled' => true,
                'driver' => 'ship-by',
                'data' => ['charge_by' => 'weight'],
            ],
        );

        $pickup = ShippingMethod::query()->updateOrCreate(
            ['code' => 'mde-pickup'],
            [
                'name' => 'Retrait entrepôt',
                'description' => 'Retrait gratuit sur site MDE Distribution',
                'enabled' => true,
                'driver' => 'collection',
                'data' => [],
            ],
        );

        $free = ShippingMethod::query()->updateOrCreate(
            ['code' => 'mde-free'],
            [
                'name' => 'Livraison offerte',
                'description' => 'Offerte dès 500 € HT sur toute la France métropolitaine',
                'enabled' => true,
                'driver' => 'free-shipping',
                'data' => ['minimum_spend' => ['EUR' => 50000]],
            ],
        );

        $this->attachRate($standard, $zone, $eur, [
            ['min_qty' => 1,  'cents' => 690],
            ['min_qty' => 5,  'cents' => 990],
            ['min_qty' => 15, 'cents' => 1490],
            ['min_qty' => 30, 'cents' => 1990],
        ]);

        $this->attachRate($pickup, $zone, $eur, [
            ['min_qty' => 1, 'cents' => 0],
        ]);

        $this->attachRate($free, $zone, $eur, [
            ['min_qty' => 1, 'cents' => 0],
        ]);
    }

    /**
     * @param  array<int, array{min_qty:int, cents:int}>  $brackets
     */
    private function attachRate(
        ShippingMethod $method,
        ShippingZone $zone,
        Currency $currency,
        array $brackets,
    ): void {
        $rate = ShippingRate::query()->updateOrCreate(
            [
                'shipping_method_id' => $method->id,
                'shipping_zone_id' => $zone->id,
            ],
            ['enabled' => true],
        );

        $rate->prices()->delete();

        foreach ($brackets as $bracket) {
            $rate->prices()->create([
                'currency_id' => $currency->id,
                'min_quantity' => $bracket['min_qty'],
                'price' => new PriceValue(
                    value: $bracket['cents'],
                    currency: $currency,
                    unitQty: 1,
                ),
            ]);
        }
    }
}

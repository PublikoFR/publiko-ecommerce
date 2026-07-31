<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Pko\Loyalty\Database\Seeders\DefaultTiersSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Model::unguard();

        $this->call([
            PkoAdminUserSeeder::class,
            PkoCurrencySeeder::class,
            PkoChannelSeeder::class,
            PkoLanguageSeeder::class,
            PkoCountrySeeder::class,
            PkoTaxSeeder::class,
            PkoCustomerGroupSeeder::class,
            // PkoShippingSeeder retiré : il seedait les 3 méthodes table-rate
            // (pko-standard / pko-pickup / pko-free) et les schedulait sur tous
            // les groupes clients, ce qui les faisait remonter au checkout à côté
            // des services Chronopost — sans UI pour les gérer (ShippingPlugin
            // retiré du panel en L1) et en doublon du franco. Le calcul des frais
            // de port passe intégralement par UnifiedShippingModifier.
            PkoShippingSurchargesSeeder::class,
            PkoBrandSeeder::class,
            PkoCollectionSeeder::class,
            PkoProductTypeSeeder::class,
            PkoProductSeeder::class,
            PkoCustomerSeeder::class,
            PkoOrderSeeder::class,
            DefaultTiersSeeder::class,
            PkoStorefrontCmsSeeder::class,
            PkoStoreSeeder::class,
            PkoMediaLibrarySeeder::class,
            AiPermissionsSeeder::class,
            ProductVideosPermissionsSeeder::class,
            CmsPermissionsSeeder::class,
        ]);

        Model::reguard();
    }
}

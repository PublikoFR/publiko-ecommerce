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
            // After customer groups: shipping methods are scheduled against them.
            PkoShippingSeeder::class,
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

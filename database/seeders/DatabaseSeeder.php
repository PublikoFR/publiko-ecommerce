<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Mde\Loyalty\Database\Seeders\DefaultTiersSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Model::unguard();

        $this->call([
            MdeAdminUserSeeder::class,
            MdeCurrencySeeder::class,
            MdeChannelSeeder::class,
            MdeLanguageSeeder::class,
            MdeCountrySeeder::class,
            MdeTaxSeeder::class,
            MdeShippingSeeder::class,
            MdeCustomerGroupSeeder::class,
            MdeBrandSeeder::class,
            MdeCollectionSeeder::class,
            MdeProductTypeSeeder::class,
            MdeProductSeeder::class,
            MdeCustomerSeeder::class,
            MdeOrderSeeder::class,
            DefaultTiersSeeder::class,
            MdeStorefrontCmsSeeder::class,
            MdeStoreSeeder::class,
        ]);

        Model::reguard();
    }
}

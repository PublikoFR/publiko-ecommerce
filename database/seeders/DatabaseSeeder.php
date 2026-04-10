<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Model::unguard();

        $this->call([
            MdeCurrencySeeder::class,
            MdeChannelSeeder::class,
            MdeLanguageSeeder::class,
            MdeCountrySeeder::class,
            MdeTaxSeeder::class,
            MdeCustomerGroupSeeder::class,
            MdeBrandSeeder::class,
            MdeCollectionSeeder::class,
            MdeProductTypeSeeder::class,
            MdeProductSeeder::class,
            MdeCustomerSeeder::class,
            MdeOrderSeeder::class,
        ]);

        Model::reguard();
    }
}

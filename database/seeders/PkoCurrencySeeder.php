<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Currency;

class PkoCurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->updateOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'exchange_rate' => 1,
                'decimal_places' => 2,
                'enabled' => true,
                'default' => true,
            ],
        );
    }
}

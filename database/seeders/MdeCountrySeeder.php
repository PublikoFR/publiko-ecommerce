<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Country;

class MdeCountrySeeder extends Seeder
{
    public function run(): void
    {
        if (Country::query()->where('iso2', 'FR')->exists()) {
            return;
        }

        Country::query()->create([
            'name' => 'France',
            'iso3' => 'FRA',
            'iso2' => 'FR',
            'phonecode' => '33',
            'capital' => 'Paris',
            'currency' => 'EUR',
            'native' => 'France',
            'emoji' => '🇫🇷',
            'emoji_u' => 'U+1F1EB U+1F1F7',
        ]);
    }
}

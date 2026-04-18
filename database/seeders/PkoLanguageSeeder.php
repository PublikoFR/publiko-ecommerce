<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Language;

class PkoLanguageSeeder extends Seeder
{
    public function run(): void
    {
        Language::query()->updateOrCreate(
            ['code' => 'fr'],
            [
                'name' => 'Français',
                'default' => true,
            ],
        );
    }
}

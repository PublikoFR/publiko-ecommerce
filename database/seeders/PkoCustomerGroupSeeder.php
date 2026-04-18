<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\CustomerGroup;

class PkoCustomerGroupSeeder extends Seeder
{
    public function run(): void
    {
        CustomerGroup::query()->updateOrCreate(
            ['handle' => 'particuliers'],
            ['name' => 'Particuliers', 'default' => true],
        );

        CustomerGroup::query()->updateOrCreate(
            ['handle' => 'installateurs'],
            ['name' => 'Installateurs', 'default' => false],
        );
    }
}

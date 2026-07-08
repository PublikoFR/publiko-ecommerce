<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Channel;

class PkoChannelSeeder extends Seeder
{
    public function run(): void
    {
        Channel::query()->updateOrCreate(
            ['handle' => 'weklo'],
            [
                'name' => 'Weklo',
                'url' => 'https://weklo.fr',
                'default' => true,
            ],
        );
    }
}

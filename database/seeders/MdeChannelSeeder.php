<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Channel;

class MdeChannelSeeder extends Seeder
{
    public function run(): void
    {
        Channel::query()->updateOrCreate(
            ['handle' => 'mde-distribution'],
            [
                'name' => 'MDE Distribution',
                'url' => 'https://mde-distribution.fr',
                'default' => true,
            ],
        );
    }
}

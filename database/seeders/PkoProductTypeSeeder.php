<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\ProductType;

class PkoProductTypeSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const TYPES = [
        'Portail',
        'Volet roulant',
        'Motorisation',
        'Clôture',
        'Accessoire',
    ];

    public function run(): void
    {
        foreach (self::TYPES as $name) {
            ProductType::query()->updateOrCreate(
                ['name' => $name],
                [],
            );
        }
    }
}

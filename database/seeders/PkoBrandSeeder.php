<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Brand;

class PkoBrandSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const BRANDS = [
        'SOMFY',
        'FAAC',
        'BFT',
        'NICE',
        'CAME',
    ];

    public function run(): void
    {
        foreach (self::BRANDS as $name) {
            Brand::query()->updateOrCreate(
                ['name' => $name],
                [],
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Pko\StoreLocator\Models\Store;

class PkoStoreSeeder extends Seeder
{
    public function run(): void
    {
        $hours = [
            'Lundi' => '8h-12h / 14h-18h',
            'Mardi' => '8h-12h / 14h-18h',
            'Mercredi' => '8h-12h / 14h-18h',
            'Jeudi' => '8h-12h / 14h-18h',
            'Vendredi' => '8h-12h / 14h-17h',
            'Samedi' => 'Fermé',
            'Dimanche' => 'Fermé',
        ];

        foreach ([
            ['slug' => 'mde-paris', 'name' => 'MDE Paris', 'address_line_1' => '12 rue de Paradis', 'postcode' => '75010', 'city' => 'Paris', 'lat' => 48.8738, 'lng' => 2.3541, 'phone' => '01 45 67 89 10'],
            ['slug' => 'mde-lyon', 'name' => 'MDE Lyon', 'address_line_1' => '45 boulevard Vivier-Merle', 'postcode' => '69003', 'city' => 'Lyon', 'lat' => 45.7601, 'lng' => 4.8574, 'phone' => '04 78 12 34 56'],
            ['slug' => 'mde-bordeaux', 'name' => 'MDE Bordeaux', 'address_line_1' => '8 cours du Médoc', 'postcode' => '33300', 'city' => 'Bordeaux', 'lat' => 44.8634, 'lng' => -0.5718, 'phone' => '05 56 78 90 12'],
            ['slug' => 'mde-marseille', 'name' => 'MDE Marseille', 'address_line_1' => '30 avenue du Prado', 'postcode' => '13008', 'city' => 'Marseille', 'lat' => 43.2722, 'lng' => 5.3888, 'phone' => '04 91 23 45 67'],
            ['slug' => 'mde-nantes', 'name' => 'MDE Nantes', 'address_line_1' => '22 rue de Strasbourg', 'postcode' => '44000', 'city' => 'Nantes', 'lat' => 47.2185, 'lng' => -1.5536, 'phone' => '02 40 12 34 56'],
            ['slug' => 'mde-toulouse', 'name' => 'MDE Toulouse', 'address_line_1' => '5 allée Jean-Jaurès', 'postcode' => '31000', 'city' => 'Toulouse', 'lat' => 43.6092, 'lng' => 1.4457, 'phone' => '05 61 23 45 67'],
        ] as $data) {
            Store::updateOrCreate(['slug' => $data['slug']], array_merge($data, [
                'country_iso2' => 'FR',
                'email' => str_replace('mde-', '', $data['slug']).'@mde-distribution.fr',
                'hours' => $hours,
                'is_active' => true,
            ]));
        }
    }
}

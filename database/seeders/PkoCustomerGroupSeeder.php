<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\CustomerGroup;

class PkoCustomerGroupSeeder extends Seeder
{
    public function run(): void
    {
        // Groupe par défaut de toute nouvelle inscription pro (cf.
        // config customer-auth.default_customer_group_handle). Non "métier" :
        // il n'apparaît pas dans la liste déroulante d'inscription.
        CustomerGroup::query()->updateOrCreate(
            ['handle' => 'nouveau-client'],
            ['name' => 'Nouveau client', 'default' => false, 'pko_is_metier' => false],
        );

        CustomerGroup::query()->updateOrCreate(
            ['handle' => 'particuliers'],
            ['name' => 'Particuliers', 'default' => true, 'pko_is_metier' => false],
        );

        // Groupes "métier" : proposés dans la liste déroulante du formulaire
        // d'inscription pour typer le nouveau client dès la création.
        foreach ([
            'installateurs' => 'Installateurs',
            'plombiers' => 'Plombiers',
            'electriciens' => 'Électriciens',
            'revendeurs' => 'Revendeurs',
        ] as $handle => $name) {
            CustomerGroup::query()->updateOrCreate(
                ['handle' => $handle],
                ['name' => $name, 'default' => false, 'pko_is_metier' => true],
            );
        }
    }
}

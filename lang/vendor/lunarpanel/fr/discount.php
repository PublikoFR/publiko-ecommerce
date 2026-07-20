<?php

declare(strict_types=1);

/*
 * Override FR partiel des traductions discount de Lunar. Laravel fusionne
 * (array_replace_recursive) ce fichier par-dessus vendor/.../lang/fr/discount.php.
 * On ne fournit que la clé `customers` : elle est absente du fichier FR de Lunar,
 * ce qui faisait retomber l'onglet « Limitations → Clients » en anglais.
 */
return [
    'relationmanagers' => [
        'customers' => [
            'title' => 'Clients',
            'description' => 'Sélectionnez les clients auxquels cette réduction doit être limitée.',
            'actions' => [
                'attach' => [
                    'label' => 'Associer un client',
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Nom',
                ],
            ],
        ],
    ],
];

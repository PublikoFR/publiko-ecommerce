<?php

declare(strict_types=1);

return [
    'credentials' => [
        'account' => env('CHRONOPOST_ACCOUNT', ''),
        'password' => env('CHRONOPOST_PASSWORD', ''),
        'sub_account' => env('CHRONOPOST_SUB_ACCOUNT', ''),
    ],

    'services' => [
        '13' => ['label' => 'Chrono 13 (avant 13h)', 'enabled' => true],
        '16' => ['label' => 'Chrono 18 (avant fin de journée)', 'enabled' => false],
        '02' => ['label' => 'Chrono Classic (J+1)', 'enabled' => true],
    ],

    'default_service' => '13',

    'grid' => [
        ['max_kg' => 2, 'price' => 1290],
        ['max_kg' => 5, 'price' => 1590],
        ['max_kg' => 10, 'price' => 1990],
        ['max_kg' => 20, 'price' => 2890],
        ['max_kg' => 30, 'price' => 3990],
    ],

    'max_weight_kg' => 30,

    'shipper' => [
        'name' => env('SHIPPER_NAME', ''),
        'street' => env('SHIPPER_STREET', ''),
        'zip' => env('SHIPPER_ZIP', ''),
        'city' => env('SHIPPER_CITY', ''),
        'country' => env('SHIPPER_COUNTRY', 'FR'),
        'phone' => env('SHIPPER_PHONE', ''),
        'email' => env('SHIPPER_EMAIL', ''),
    ],

    /*
     * Correspondance code de service interne → code produit Chronopost, utilisée en
     * secours quand `pko_carrier_services.carrier_product_code` est vide (tests, install
     * neuve). Valeurs issues du module PrestaShop officiel v7.5.6 (compte standard).
     */
    'product_codes' => [
        'chrono_relais' => '86',
        'chrono13' => '1',
        'chrono10' => '2',
        'chrono18' => '16',
        'chrono_classic' => '44',
    ],

    'packaging' => [
        'default_weight_unit' => 'KGM',
        'default_dim_unit' => 'CMT',
        // Carton par défaut quand les variantes de la commande ne portent pas
        // les trois dimensions.
        'default_dimensions_cm' => [
            'length' => 30,
            'width' => 20,
            'height' => 15,
        ],
    ],

    'label_format' => 'PDF',
];

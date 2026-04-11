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
        'name' => env('MDE_SHIPPER_NAME', 'MDE Distribution'),
        'street' => env('MDE_SHIPPER_STREET', ''),
        'zip' => env('MDE_SHIPPER_ZIP', ''),
        'city' => env('MDE_SHIPPER_CITY', ''),
        'country' => env('MDE_SHIPPER_COUNTRY', 'FR'),
        'phone' => env('MDE_SHIPPER_PHONE', ''),
        'email' => env('MDE_SHIPPER_EMAIL', ''),
    ],

    'packaging' => [
        'default_weight_unit' => 'KGM',
        'default_dim_unit' => 'CMT',
    ],

    'label_format' => 'PDF',
];

<?php

declare(strict_types=1);

return [
    'credentials' => [
        'contract_number' => env('COLISSIMO_CONTRACT', ''),
        'password' => env('COLISSIMO_PASSWORD', ''),
    ],

    'services' => [
        'DOM' => ['label' => 'Colissimo Domicile (sans signature)', 'enabled' => true],
        'DOS' => ['label' => 'Colissimo Domicile (avec signature)', 'enabled' => true],
    ],

    'default_service' => 'DOM',

    'grid' => [
        ['max_kg' => 0.25, 'price' => 590],
        ['max_kg' => 0.5, 'price' => 790],
        ['max_kg' => 1, 'price' => 890],
        ['max_kg' => 2, 'price' => 1090],
        ['max_kg' => 5, 'price' => 1490],
        ['max_kg' => 10, 'price' => 1890],
        ['max_kg' => 15, 'price' => 2390],
        ['max_kg' => 30, 'price' => 2890],
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

    'wsdl_url' => env('COLISSIMO_WSDL_URL', 'https://ws.colissimo.fr/sls-ws/SlsServiceWS?wsdl'),

    'output_format' => [
        'x' => 0,
        'y' => 0,
        'output_printing_type' => 'PDF_A4_300dpi',
    ],
];

<?php

return [

    'default' => env('PAYMENTS_TYPE', 'card'),

    'types' => [
        'card' => [
            'driver' => 'stripe',
            'authorized' => 'payment-received',
        ],
        'sepa' => [
            'driver' => 'stripe',
            'authorized' => 'payment-received',
        ],
    ],

];

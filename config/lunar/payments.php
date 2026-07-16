<?php

return [

    'default' => env('PAYMENTS_TYPE', 'card'),

    'types' => [
        'card' => [
            'driver' => 'stripe',
            'authorized' => 'payment-received',
        ],
    ],

];

<?php

declare(strict_types=1);

return [
    'default_ratio' => env('LOYALTY_DEFAULT_RATIO', 1),
    'admin_email' => env('LOYALTY_ADMIN_EMAIL'),

    /*
     * BFA (bonus de fin d'année) : taux de TVA de l'avoir émis. Une remise de
     * fin d'année réduit la base taxable des ventes : même taux qu'elles.
     */
    'bfa' => [
        'vat_rate' => (float) env('LOYALTY_BFA_VAT_RATE', 20),
    ],
];

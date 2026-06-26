<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Franco de port — seuil HT
    |--------------------------------------------------------------------------
    |
    | Montant minimum en centimes HT (hors taxe) de produits éligibles pour
    | que la livraison standard Chrono 13 soit offerte automatiquement.
    |
    | Variable d'env : FRANCO_THRESHOLD_HT_CENTS
    | Défaut         : 35000 (= 350,00 €)
    |
    */
    'franco' => [
        'threshold_ht_cents' => (int) env('FRANCO_THRESHOLD_HT_CENTS', 35000),
    ],

];

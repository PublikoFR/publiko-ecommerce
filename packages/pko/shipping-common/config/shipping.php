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

    /*
    |--------------------------------------------------------------------------
    | Base de taxe des frais de port
    |--------------------------------------------------------------------------
    |
    | price_base : nature des prix de grille transporteur stockés en DB.
    |   - 'ht'  (défaut) : prix nets (hors taxe). La TaxClass par défaut est
    |                      appliquée → la TVA est ajoutée par-dessus. Conforme
    |                      aux grilles La Poste/Chronopost (cf. docs/shipping.md §5.9).
    |   - 'ttc'          : prix TTC (taxe incluse). Le modifier reconvertit en
    |                      net via le taux réel de la TaxClass par défaut puis
    |                      applique cette même classe → la TVA reste correctement
    |                      ventilée et le total payé = prix de grille.
    |
    | display : affichage panier des prix d'expédition ('both' | 'ht' | 'ttc').
    |
    | Variables d'env : SHIPPING_TAX_PRICE_BASE, SHIPPING_TAX_DISPLAY
    |
    */
    'tax' => [
        'price_base' => env('SHIPPING_TAX_PRICE_BASE', 'ht'),
        'display' => env('SHIPPING_TAX_DISPLAY', 'both'),
    ],

];

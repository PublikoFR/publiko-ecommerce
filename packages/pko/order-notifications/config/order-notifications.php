<?php

declare(strict_types=1);

return [
    /*
     * Destination du bouton « Donner mon avis » (e-mail 13). Vide = e-mail
     * jamais envoyé. Renseigner l'URL de la plateforme d'avis retenue.
     */
    'review_url' => env('ORDER_REVIEW_URL', ''),

    /*
     * Délai, en jours après la livraison, avant la demande d'avis (e-mail 13).
     */
    'review_delay_days' => (int) env('ORDER_REVIEW_DELAY_DAYS', 7),

    /*
     * Délai d'inactivité, en heures, avant la relance panier (e-mail 11).
     */
    'abandoned_cart_hours' => (int) env('ABANDONED_CART_HOURS', 24),

    /*
     * Délai, en jours, avant la relance d'un devis sans suite (e-mail 10).
     */
    'quote_reminder_days' => (int) env('QUOTE_REMINDER_DAYS', 5),
];

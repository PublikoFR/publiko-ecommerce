<?php

declare(strict_types=1);

return [
    'drivers' => [
        'stripe' => 'Paiement en ligne',
        'offline' => 'Paiement hors ligne',
        'cash' => 'Espèces',
        'paypal' => 'PayPal',
    ],
    'statuses' => [
        'succeeded' => 'Réussi',
        'success' => 'Réussi',
        'pending' => 'En attente',
        'processing' => 'En cours de traitement',
        'failed' => 'Échoué',
        'cancelled' => 'Annulé',
        'canceled' => 'Annulé',
        'refunded' => 'Remboursé',
        'requires-capture' => 'Capture requise',
        'requires_capture' => 'Capture requise',
        'requires_payment_method' => 'Moyen de paiement requis',
        'requires_confirmation' => 'Confirmation requise',
        'requires_action' => 'Action requise',
        'auth-pending' => 'Autorisation en attente',
        'authorized' => 'Autorisé',
        'capture' => 'Capturé',
        'intent' => 'Intention',
        'refund' => 'Remboursé',
        'awaiting-payment' => 'En attente de paiement',
    ],
];

<?php

declare(strict_types=1);

return [
    /*
     * Kill-switch global. À false, aucune requête n'est envoyée à Pennylane :
     * pas de facture, pas d'avoir, pas de polling. À couper sur tout
     * environnement non-production (dev, staging) pour éviter de polluer la
     * comptabilité réelle avec des commandes de test.
     */
    'enabled' => filter_var(env('PENNYLANE_ENABLED', true), FILTER_VALIDATE_BOOL),

    'api_token' => env('PENNYLANE_API_TOKEN'),
    'base_url' => env('PENNYLANE_BASE_URL', 'https://app.pennylane.com/api/external/v2'),
    'sandbox' => env('PENNYLANE_SANDBOX', false),

    /*
     * Lien « Ouvrir dans Pennylane » de la fiche facture admin. L'API ne
     * renvoie aucune URL de l'application web : on la construit depuis ce
     * gabarit, `{id}` étant l'identifiant Pennylane du document. Copier l'URL
     * d'une facture ouverte dans Pennylane et remplacer son id par `{id}`.
     * Vide → le bouton ouvre simplement l'application Pennylane.
     */
    'app_invoice_url' => env('PENNYLANE_APP_INVOICE_URL'),

    'customer_invoice_template_id' => env('PENNYLANE_INVOICE_TEMPLATE_ID'),
    'default_payment_deadline_days' => (int) env('PENNYLANE_DEADLINE_DAYS', 0),
    'default_language' => env('PENNYLANE_LANG', 'fr'),

    'trigger_on_status' => env('PENNYLANE_TRIGGER_STATUS', 'payment-received'),
    'auto_credit_note_on_refund' => env('PENNYLANE_AUTO_CREDIT_NOTE', true),

    /*
     * Bon de livraison (commercial document `shipping_order`) émis à
     * l'expédition : pièce justificative de la livraison en cas de litige.
     * Quand son statut est aussi celui de la facture (`trigger_on_status`),
     * la facture n'est émise qu'APRÈS le bon de livraison, qu'elle cite.
     * Demande le scope `commercial_documents:all` sur le token.
     */
    'delivery_note' => [
        'enabled' => filter_var(env('PENNYLANE_DELIVERY_NOTE', false), FILTER_VALIDATE_BOOL),
        'trigger_on_status' => env('PENNYLANE_DELIVERY_NOTE_STATUS', 'dispatched'),
    ],

    'queue' => env('PENNYLANE_QUEUE', 'default'),

    'http' => [
        'timeout' => (int) env('PENNYLANE_HTTP_TIMEOUT', 15),
        'retry_times' => (int) env('PENNYLANE_HTTP_RETRY', 3),
        'retry_sleep_ms' => (int) env('PENNYLANE_HTTP_RETRY_SLEEP', 300),
    ],

    'external_reference_prefix' => [
        'invoice' => env('PENNYLANE_EXT_PREFIX_INVOICE', 'order_'),
        'credit_note' => env('PENNYLANE_EXT_PREFIX_CREDIT', 'refund_'),
        'delivery_note' => env('PENNYLANE_EXT_PREFIX_DELIVERY', 'delivery_'),
        'customer' => env('PENNYLANE_EXT_PREFIX_CUSTOMER', 'lunar_cust_'),
    ],
];

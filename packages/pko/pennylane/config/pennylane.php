<?php

declare(strict_types=1);

return [
    'api_token' => env('PENNYLANE_API_TOKEN'),
    'base_url' => env('PENNYLANE_BASE_URL', 'https://app.pennylane.com/api/external/v2'),
    'sandbox' => env('PENNYLANE_SANDBOX', false),

    'customer_invoice_template_id' => env('PENNYLANE_INVOICE_TEMPLATE_ID'),
    'default_payment_deadline_days' => (int) env('PENNYLANE_DEADLINE_DAYS', 0),
    'default_language' => env('PENNYLANE_LANG', 'fr'),

    'trigger_on_status' => env('PENNYLANE_TRIGGER_STATUS', 'payment-received'),
    'auto_credit_note_on_refund' => env('PENNYLANE_AUTO_CREDIT_NOTE', true),

    'queue' => env('PENNYLANE_QUEUE', 'default'),

    'http' => [
        'timeout' => (int) env('PENNYLANE_HTTP_TIMEOUT', 15),
        'retry_times' => (int) env('PENNYLANE_HTTP_RETRY', 3),
        'retry_sleep_ms' => (int) env('PENNYLANE_HTTP_RETRY_SLEEP', 300),
    ],

    'external_reference_prefix' => [
        'invoice' => env('PENNYLANE_EXT_PREFIX_INVOICE', 'order_'),
        'credit_note' => env('PENNYLANE_EXT_PREFIX_CREDIT', 'refund_'),
        'customer' => env('PENNYLANE_EXT_PREFIX_CUSTOMER', 'lunar_cust_'),
    ],
];

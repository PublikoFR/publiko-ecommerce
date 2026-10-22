<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | LLM
    |--------------------------------------------------------------------------
    */

    'llm' => [
        'http_timeout' => 60,

        // Retry réservé aux erreurs transitoires (réseau, 408/409/429, 5xx) :
        // une 4xx définitive (400, 401, 402, 403…) n'est jamais retentée.
        'retries' => 3,
        'retry_backoff_ms' => [2000, 5000, 10000],
        'retryable_status_codes' => [408, 409, 429],

        // Plafond de tokens générés par appel quand ni l'action ni la
        // configuration LLM (`options.max_tokens`) ne le précisent.
        'default_max_tokens' => 2048,
    ],

];

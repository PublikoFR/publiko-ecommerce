<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'disk' => env('AI_IMPORTER_DISK', 'local'),
        'inputs_path' => 'ai-importer/inputs',
        'outputs_path' => 'ai-importer/outputs',
        'backups_path' => 'ai-importer/backups',
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload
    |--------------------------------------------------------------------------
    */

    'upload' => [
        'max_size_kb' => (int) env('AI_IMPORTER_MAX_UPLOAD_KB', 102_400), // 100 MB
        'accepted_mimes' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv',
            'application/csv',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    */

    'queues' => [
        'parse' => env('AI_IMPORTER_PARSE_QUEUE', 'ai-importer-parse'),
        'import' => env('AI_IMPORTER_IMPORT_QUEUE', 'ai-importer-import'),
        'connection' => env('AI_IMPORTER_QUEUE_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipeline defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'chunk_size' => 500,
        'error_policy' => 'ignore', // ignore | stop | rollback
        'checkpoint_every' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM
    |--------------------------------------------------------------------------
    */

    'llm' => [
        'http_timeout' => 60,
        'retries' => 3,
        'retry_backoff_ms' => [2000, 5000, 10000],
        'critical_status_codes' => [401, 402, 403],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'group' => 'Imports',
        'sort' => 70,
    ],
];

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
            // Un CSV est souvent détecté « text/plain », et un XLSX produit par
            // certains outils « application/zip » : sans eux le fichier était refusé
            // à l'envoi. Un fichier illisible est de toute façon signalé par le
            // contrôle du fichier, avant toute préparation.
            'text/plain',
            'application/zip',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    */

    // Les jobs d'import tournent jusqu'à 1 h (parse) / 2 h (import). Ils partent
    // sur des files dédiées, consommées par un worker planifié SÉPARÉ du worker
    // `default` (routes/console.php) : un long import ne bloque ni factures ni mails.
    // Connexion : une variante « -long » de la connexion par défaut, dont le
    // `retry_after` (config/queue.php) dépasse le timeout des jobs — sinon un job
    // encore en cours serait réservé une seconde fois et exécuté en double.
    // null = connexion par défaut (cas `sync` : exécution en ligne). Le repli sur
    // `database` reprend celui de config/queue.php : les deux doivent rester alignés.
    'queues' => [
        'parse' => env('AI_IMPORTER_PARSE_QUEUE', 'ai-importer-parse'),
        'import' => env('AI_IMPORTER_IMPORT_QUEUE', 'ai-importer-import'),
        'connection' => env('AI_IMPORTER_QUEUE_CONNECTION') ?: match (env('QUEUE_CONNECTION', 'database')) {
            'database' => 'database-long',
            'redis' => 'redis-long',
            default => null,
        },
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
        // Délai max (secondes) entre deux écritures des logs ligne à ligne et
        // de la progression, quel que soit `checkpoint_every`.
        'log_flush_seconds' => 2,
        // Taille (octets) au-delà de laquelle un XLSX/XLS est lu en mode streamé
        // (fenêtres de `chunk_size` lignes) pour plafonner la mémoire.
        'stream_threshold_bytes' => (int) env('AI_IMPORTER_STREAM_THRESHOLD_BYTES', 5 * 1024 * 1024),
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

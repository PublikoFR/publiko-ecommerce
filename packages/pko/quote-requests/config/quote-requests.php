<?php

declare(strict_types=1);

return [
    /*
     * Pièces jointes : taille maximale par fichier (en Ko) et nombre maximal
     * de fichiers par demande. Livewire refuse en amont tout fichier
     * temporaire au-delà de `livewire.temporary_file_upload.rules` (12 Mo par
     * défaut) : relever ce plafond-là aussi pour dépasser 12 Mo.
     */
    'attachments' => [
        'max_size_kb' => (int) env('QUOTE_REQUEST_MAX_FILE_KB', 10240),
        'max_files' => (int) env('QUOTE_REQUEST_MAX_FILES', 8),
        // Disque PRIVÉ : les fichiers ne sont jamais servis publiquement, on
        // les télécharge depuis l'admin (route protégée par la policy).
        'disk' => env('QUOTE_REQUEST_DISK', 'local'),
        'directory' => 'quote-requests',
        // Cumul maximal (en Ko) des fichiers attachés au mail équipe ; au-delà,
        // le mail renvoie vers l'admin. 15 Mo ≈ 20 Mo encodés (limite SMTP
        // courante : 25 Mo).
        'mail_max_total_kb' => (int) env('QUOTE_REQUEST_MAIL_ATTACHMENTS_KB', 15360),
    ],

    /*
     * Lignes produit / quantité saisies par le demandeur.
     */
    'max_lines' => 30,

    /*
     * Anti-spam : nombre de demandes acceptées par fenêtre glissante, par IP.
     */
    'rate_limit' => [
        'max_attempts' => (int) env('QUOTE_REQUEST_RATE_LIMIT', 5),
        'decay_seconds' => 3600,
    ],
];

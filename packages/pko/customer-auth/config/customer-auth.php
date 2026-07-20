<?php

declare(strict_types=1);

return [

    'sirene' => [
        'enabled' => (bool) env('INSEE_ENABLED', false),
        // Nouveau portail INSEE (portail-api.insee.fr) : clé API unique en en-tête,
        // plus d'OAuth. Endpoint /siret/{siret} sous /api-sirene/3.11.
        'base_url' => env('INSEE_BASE_URL', 'https://api.insee.fr/api-sirene/3.11'),
        'api_key' => env('INSEE_API_KEY'),
        'api_key_header' => env('INSEE_API_KEY_HEADER', 'X-INSEE-Api-Key-Integration'),
        'timeout' => (int) env('INSEE_TIMEOUT', 5),
    ],

    // Groupe attribué d'office à toute nouvelle inscription pro et cible de
    // réattribution lorsqu'un groupe client est supprimé.
    'default_customer_group_handle' => env('DEFAULT_PRO_GROUP_HANDLE', 'nouveau-client'),

    'admin_notification_email' => env('ADMIN_NOTIFICATION_EMAIL', env('CONTACT_EMAIL')),

    'allowed_nafs' => [],

];

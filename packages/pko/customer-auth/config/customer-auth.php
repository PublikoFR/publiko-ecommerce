<?php

declare(strict_types=1);

return [

    'sirene' => [
        'enabled' => (bool) env('INSEE_ENABLED', false),
        'base_url' => env('INSEE_BASE_URL', 'https://api.insee.fr/entreprises/sirene/V3.11'),
        'consumer_key' => env('INSEE_API_KEY'),
        'consumer_secret' => env('INSEE_API_SECRET'),
        'timeout' => (int) env('INSEE_TIMEOUT', 5),
        'cache_token_hours' => (int) env('INSEE_CACHE_TOKEN_HOURS', 6),
    ],

    'default_customer_group_handle' => env('DEFAULT_PRO_GROUP_HANDLE', 'installateurs'),

    'admin_notification_email' => env('ADMIN_NOTIFICATION_EMAIL', env('CONTACT_EMAIL')),

    'allowed_nafs' => [],

];

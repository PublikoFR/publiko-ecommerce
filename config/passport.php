<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard (consent OAuth)
    |--------------------------------------------------------------------------
    |
    | Guard utilisé par l'écran de consentement `/oauth/authorize` pour
    | identifier l'utilisateur qui autorise le connecteur. On pointe sur le
    | guard `staff` (session Filament) : c'est le personnel back-office qui
    | autorise le connecteur claude.ai, pas un client du storefront.
    |
    | La validation des access tokens côté MCP se fait, elle, via le guard
    | `api` (driver passport, provider `oauth_staff`) — cf. config/auth.php.
    |
    */

    'guard' => 'staff',

];

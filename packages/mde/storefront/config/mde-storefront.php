<?php

declare(strict_types=1);

return [
    'contact' => [
        'phone' => env('MDE_CONTACT_PHONE', '02 XX XX XX XX'),
        'email' => env('MDE_CONTACT_EMAIL', 'contact@mde-distribution.fr'),
        'tagline' => env('MDE_CONTACT_TAGLINE', 'Besoin d\'un conseil ?'),
    ],

    'social' => [
        'facebook' => env('MDE_SOCIAL_FACEBOOK'),
        'instagram' => env('MDE_SOCIAL_INSTAGRAM'),
        'linkedin' => env('MDE_SOCIAL_LINKEDIN'),
        'youtube' => env('MDE_SOCIAL_YOUTUBE'),
    ],

    'banner' => [
        'enabled' => env('MDE_BANNER_ENABLED', true),
        'text' => env('MDE_BANNER_TEXT', 'Livraison offerte dès 125 € HT'),
        'icon' => 'truck',
    ],

    'shipping' => [
        'free_threshold_cents' => (int) env('MDE_MIN_FREE_SHIPPING_CENTS', 12500),
    ],

    'usps' => [
        ['icon' => 'map-pin', 'title' => 'Plus de 80 magasins', 'subtitle' => 'Partout en France'],
        ['icon' => 'users', 'title' => '1 700 personnes', 'subtitle' => 'À votre service'],
        ['icon' => 'truck', 'title' => '60 000 références', 'subtitle' => 'Disponibles en 24h'],
        ['icon' => 'credit-card', 'title' => 'À partir de 125 € HT', 'subtitle' => 'Livraison offerte'],
    ],

    'home' => [
        'featured_collection_slug' => env('MDE_HOME_FEATURED_COLLECTION', null),
    ],

    'nav' => [
        'secondary' => [
            ['label' => 'Tous nos produits', 'route' => null, 'mega' => true],
            ['label' => 'Nouveautés', 'route' => null, 'href' => '/collections#nouveautes'],
            ['label' => 'Exclusivités MDE', 'route' => null, 'href' => '/collections'],
            ['label' => 'Nos magasins', 'route' => null, 'href' => '/magasins'],
            ['label' => 'Actualités', 'route' => null, 'href' => '/actualites'],
        ],
    ],
];

<?php

declare(strict_types=1);

return [
    'contact' => [
        'phone' => env('CONTACT_PHONE', '02 XX XX XX XX'),
        'email' => env('CONTACT_EMAIL', ''),
        'tagline' => env('CONTACT_TAGLINE', 'Besoin d\'un conseil ?'),
    ],

    'social' => [
        'facebook' => env('SOCIAL_FACEBOOK'),
        'instagram' => env('SOCIAL_INSTAGRAM'),
        'linkedin' => env('SOCIAL_LINKEDIN'),
        'youtube' => env('SOCIAL_YOUTUBE'),
    ],

    'banner' => [
        'enabled' => env('BANNER_ENABLED', true),
        'text' => env('BANNER_TEXT', 'Livraison offerte dès 125 € HT'),
        'icon' => 'truck',
    ],

    'shipping' => [
        'free_threshold_cents' => (int) env('MIN_FREE_SHIPPING_CENTS', 12500),
    ],

    'usps' => [
        ['icon' => 'map-pin', 'title' => 'Plus de 80 magasins', 'subtitle' => 'Partout en France'],
        ['icon' => 'users', 'title' => '1 700 personnes', 'subtitle' => 'À votre service'],
        ['icon' => 'truck', 'title' => '60 000 références', 'subtitle' => 'Disponibles en 24h'],
        ['icon' => 'credit-card', 'title' => 'À partir de 125 € HT', 'subtitle' => 'Livraison offerte'],
    ],

    'home' => [
        'featured_collection_slug' => env('HOME_FEATURED_COLLECTION', null),
    ],

    'nav' => [
        'secondary' => [
            ['label' => 'Tous nos produits', 'route' => null, 'mega' => true],
            ['label' => 'Nouveautés', 'route' => null, 'href' => '/collections#nouveautes'],
            ['label' => 'Exclusivités', 'route' => null, 'href' => '/collections'],
            ['label' => 'Nos magasins', 'route' => null, 'href' => '/magasins'],
            ['label' => 'Actualités', 'route' => null, 'href' => '/actualites'],
        ],
    ],
];

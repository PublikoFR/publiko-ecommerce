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

    // Les textes ci-dessous acceptent les variables dynamiques de
    // Pko\Storefront\Support\StorefrontText (ex. {{port_franco}} → « 500 € HT »),
    // résolues au rendu depuis ShippingSettings. Ne jamais recopier le seuil de
    // franco en dur : il ne se répercuterait pas quand on le modifie en back-office.
    'banner' => [
        'enabled' => env('BANNER_ENABLED', true),
        'text' => env('BANNER_TEXT', 'Livraison offerte dès {{port_franco}}'),
        'icon' => 'truck',
    ],

    'usps' => [
        ['icon' => 'map-pin', 'title' => 'Plus de 80 magasins', 'subtitle' => 'Partout en France'],
        ['icon' => 'users', 'title' => '1 700 personnes', 'subtitle' => 'À votre service'],
        ['icon' => 'truck', 'title' => '60 000 références', 'subtitle' => 'Disponibles en 24h'],
        ['icon' => 'credit-card', 'title' => 'À partir de {{port_franco}}', 'subtitle' => 'Livraison offerte'],
    ],

    'home' => [
        'featured_collection_slug' => env('HOME_FEATURED_COLLECTION', null),
    ],

    'nav' => [
        'secondary' => [
            // « Tous nos produits » est rendu par le bouton burger dédié du
            // header (déclencheur du menu latéral off-canvas) — ne pas le
            // redupliquer ici, sinon il apparaît en double sous forme de lien mort.
            ['label' => 'Nouveautés', 'route' => null, 'href' => '/collections#nouveautes'],
            ['label' => 'Exclusivités', 'route' => null, 'href' => '/collections'],
            ['label' => 'Nos magasins', 'route' => null, 'href' => '/magasins'],
            ['label' => 'Actualités', 'route' => null, 'href' => '/actualites'],
        ],
    ],
];

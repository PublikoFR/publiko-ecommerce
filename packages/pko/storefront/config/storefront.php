<?php

declare(strict_types=1);

return [
    /*
     * Pays de la boutique (ISO 3166-1 alpha-2), pré-sélectionné au checkout.
     * Repli sur SHIPPER_COUNTRY, déjà renseigné pour l'expéditeur transporteur.
     * Sans ce réglage, le formulaire retombait sur le premier pays de la table
     * par ordre alphabétique — l'Afghanistan.
     */
    'country' => env('STOREFRONT_COUNTRY', env('SHIPPER_COUNTRY', 'FR')),

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

    // Bandeau au-dessus du menu (forest) : accroche de marque (brand_tagline)
    // + info franco (banner.*) à gauche, liens utilitaires à droite.
    'topbar' => [
        'links' => [
            ['label' => 'Questions fréquentes', 'href' => '/pages/faq'],
            ['label' => 'Nous contacter', 'href' => '/contact'],
        ],
    ],

    // Bandeau d'information sous le menu. Chaque texte accepte les variables
    // StorefrontText ({{port_franco}}…). Liste vide = bandeau masqué.
    'access_band' => [
        'items' => [
            ['icon' => 'book-open-check', 'text' => 'Catalogue & notices en accès libre'],
            ['icon' => 'lock', 'text' => 'Tarifs & commandes après validation du compte pro'],
        ],
    ],

    // Conservés pour compatibilité (réglage admin historique), plus rendus
    // depuis FEAT-009 : le footer ne reprend que les liens de la maquette.
    'usps' => [],

    'home' => [
        'featured_collection_slug' => env('HOME_FEATURED_COLLECTION', null),

        // Rangée de raccourcis services sous le schéma (remplacement, identification
        // de pièce, devis projet). Lien vide = bouton masqué.
        'services' => [
            ['icon' => 'refresh-cw', 'title' => 'Remplacement moteur volet roulant', 'subtitle' => 'Trouvez la solution adaptée', 'href' => '/pages/remplacement-moteur-volet-roulant'],
            ['icon' => 'camera', 'title' => "Identification d'une pièce", 'subtitle' => 'Transmettez-nous vos photos', 'href' => '/pages/identifier-une-piece'],
            ['icon' => 'clipboard-pen', 'title' => 'Devis pour un projet', 'subtitle' => 'Présentez-nous vos besoins', 'href' => '/contact'],
        ],

        // « La sélection pour les pros » : 3 raccourcis. Lien vide = carte masquée.
        'selection' => [
            ['icon' => 'tag', 'title' => 'Offres du moment', 'subtitle' => 'Les sélections à découvrir', 'href' => '/pages/offres-du-moment'],
            ['icon' => 'sparkles', 'title' => 'Nouveautés', 'subtitle' => 'Les dernières références', 'href' => '/collections#nouveautes'],
            ['icon' => 'package-open', 'title' => 'Déstockage', 'subtitle' => 'Les opportunités du catalogue', 'href' => '/pages/destockage'],
        ],

        // Ligne « communauté » du bandeau pro : masquée tant que l'URL est vide.
        'community_url' => null,

        // Module « À chaque installation, sa solution » : une entrée par zone du
        // schéma (cf. Pko\StorefrontCms\Support\HomeSolutionZones). Une zone sans
        // collection associée n'est pas affichée. Le mapping se fait en admin
        // (Page d'accueil → Sections).
        'solutions' => [],
    ],

    'nav' => [
        // « Tous nos produits » est rendu par le bouton burger dédié du header
        // (déclencheur du menu latéral off-canvas) — ne pas le redupliquer ici.
        // Un lien sans URL est masqué (pages à créer dans le CMS).
        'secondary' => [
            ['label' => 'Pièces détachées', 'href' => '/collections/pieces-detachees'],
            ['label' => 'Configurateur sur mesure', 'href' => '/pages/configurateur'],
            ['label' => 'Nos exclusivités', 'href' => '/pages/exclusivites'],
            ['label' => 'Notices & documents', 'href' => '/pages/notices-documents'],
            ['label' => 'Offres pro', 'href' => '/pages/offres-pro'],
        ],
        'quote_url' => '/contact',
    ],

    'footer' => [
        'columns' => [
            ['title' => 'Votre accompagnement', 'links' => [
                ['label' => 'Demander un devis', 'href' => '/contact'],
                ['label' => 'Identifier une pièce', 'href' => '/pages/identifier-une-piece'],
                ['label' => 'Questions fréquentes', 'href' => '/pages/faq'],
            ]],
            ['title' => 'À votre disposition', 'links' => [
                ['label' => 'Notices & documents', 'href' => '/pages/notices-documents'],
                ['label' => 'La communauté', 'href' => null],
                ['label' => 'Actualités', 'href' => '/actualites'],
            ]],
            ['title' => 'Informations', 'links' => [
                ['label' => 'Mentions légales', 'href' => '/pages/mentions-legales'],
                ['label' => 'Conditions générales', 'href' => '/pages/cgv'],
                ['label' => 'Confidentialité', 'href' => '/pages/politique-donnees'],
            ]],
        ],
        'baseline' => 'Catalogue ouvert à tous · Achat réservé aux professionnels validés',
    ],
];

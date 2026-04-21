<?php

declare(strict_types=1);

return [
    'groups' => [
        'pilotage' => 'Pilotage',
        'catalogue' => 'Catalogue',
        'catalogue_settings' => 'Paramètres catalogue',
        'sales' => 'Ventes & Clients',
        'content' => 'Contenu',
        'config' => 'Configuration',
        'config_general' => 'Général',
        'config_imports' => 'Imports et Données',
        'config_shop' => 'Boutique',
        'config_payment' => 'Paiement & Expédition',
    ],
    'shortcuts' => [
        'dashboard' => 'Tableau de bord',
        'orders' => 'Commandes',
        'shipping' => 'Expédition',
        'customers' => 'Clients',
    ],
    'hubs' => [
        'loyalty' => [
            'nav' => 'Fidélité',
            'title' => 'Fidélité',
            'tabs' => [
                'tiers' => 'Paliers',
                'gifts' => 'Cadeaux débloqués',
                'points' => 'Historique des points',
                'settings' => 'Configuration',
            ],
            'actions' => [
                'create_tier' => 'Nouveau palier',
            ],
            'fields' => [
                'points_ratio' => [
                    'label' => 'Ratio €HT / point',
                    'help' => 'Montant en euros HT pour obtenir 1 point (ex: 1 = 1€HT = 1 point).',
                ],
                'admin_email' => [
                    'label' => 'Email admin (notifications)',
                    'help' => 'Destinataire de la notification quand un client débloque un palier.',
                ],
            ],
            'settings' => [
                'save' => 'Enregistrer',
                'saved' => 'Configuration enregistrée',
            ],
        ],
        'homepage' => [
            'nav' => 'Page d\'accueil',
            'title' => 'Page d\'accueil',
            'tabs' => [
                'slides' => 'Slides',
                'tiles' => 'Tuiles',
                'offers' => 'Offres du moment',
            ],
            'actions' => [
                'create_slide' => 'Nouveau slide',
                'create_tile' => 'Nouvelle tuile',
                'create_offer' => 'Nouvelle offre',
            ],
        ],
    ],
];

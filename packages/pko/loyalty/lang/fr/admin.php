<?php

declare(strict_types=1);

return [
    'settings' => [
        'nav' => 'Configuration fidélité',
        'title' => 'Configuration du programme de fidélité',
    ],
    'bfa' => [
        'nav' => 'BFA de fin d\'année',
        'title' => 'Bonus de fin d\'année (BFA)',
        'subheading' => 'Pré-calculez les BFA de l\'année, ajustez les montants si besoin, puis émettez les avoirs comptables. Une ligne par tranche : chaque palier BFA débloqué compte jusqu\'au palier BFA suivant ou au 31/12.',
        'precompute' => 'Pré-calculer :year',
        'precomputed' => ':count tranche(s) à jour.',
        'emit_all' => 'Émettre les avoirs :year',
        'emit_selected' => 'Émettre les avoirs',
        'emit' => 'Émettre l\'avoir',
        'emit_confirm' => 'Un avoir sera émis dans la comptabilité pour chaque ligne à émettre (montant non nul). Une ligne déjà émise n\'est jamais émise deux fois.',
        'emitted' => 'Avoirs émis : :issued — ignorés : :skipped — en échec : :failed',
        'year_open' => 'L\'année n\'est pas terminée : les tranches courent jusqu\'au 31/12.',
        'adjust' => 'Ajuster',
        'adjusted' => 'Montant ajusté',
        'amount_eur' => 'Montant à verser',
        'amount_help' => 'En euros HT. Laisser le montant calculé pour revenir au pré-calcul.',
        'empty' => 'Aucun BFA pour cette année',
        'empty_help' => 'Lancez le pré-calcul pour lister les tranches des clients ayant débloqué un palier BFA.',
        'columns' => [
            'customer' => 'Client',
            'tier' => 'Palier',
            'period' => 'Tranche',
            'revenue' => 'CA HT net',
            'computed' => 'BFA calculé',
            'to_issue' => 'À verser (HT)',
            'status' => 'Statut',
            'credit_note' => 'N° d\'avoir',
            'year' => 'Année',
        ],
    ],
];

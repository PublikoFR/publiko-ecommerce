<?php

declare(strict_types=1);

return [
    'cluster' => [
        'nav' => 'Pennylane',
    ],
    'config' => [
        'nav' => 'Pennylane',
        'title' => 'Configuration Pennylane',
        'credentials' => 'Credentials Pennylane',
        'api_token' => 'API Token',
        'template_id' => 'Template ID (facture)',
        'deadline_days' => 'Délai de paiement (jours)',
        'trigger_status' => 'Statut déclencheur',
        'trigger_status_help' => 'Statut de commande Lunar qui déclenche la création automatique de facture.',
        'auto_credit_note' => 'Avoir automatique sur remboursement',
        'auto_credit_note_help' => 'Émet automatiquement un avoir Pennylane quand une transaction de type refund est créée sur une commande.',
        'language' => 'Langue des factures',
        'save' => 'Enregistrer',
        'saved' => 'Configuration enregistrée.',
        'test' => 'Tester la connexion',
        'test_success' => 'Connexion Pennylane réussie',
        'test_failure' => 'Échec de connexion Pennylane',
        'status' => [
            'title' => 'État de la configuration',
            'token' => 'Token API',
            'template' => 'Template facture',
            'source' => 'Source des secrets',
            'missing' => 'Non configuré',
        ],
    ],
    'invoice' => [
        'nav' => 'Factures émises',
        'singular' => 'Facture Pennylane',
        'plural' => 'Factures Pennylane',
        'fields' => [
            'order_id' => 'Commande',
            'transaction_id' => 'Transaction',
            'type' => 'Type',
            'pennylane_id' => 'ID Pennylane',
            'pennylane_invoice_number' => 'N° facture',
            'external_reference' => 'Référence externe',
            'status' => 'Statut',
            'last_error' => 'Dernière erreur',
            'synced_at' => 'Synchronisée le',
        ],
        'types' => [
            'invoice' => 'Facture',
            'credit_note' => 'Avoir',
        ],
        'statuses' => [
            'draft' => 'Brouillon',
            'finalized' => 'Finalisée',
            'failed' => 'Échec',
        ],
        'actions' => [
            'resync' => 'Resynchroniser',
            'resync_success' => 'Resynchronisation dispatchée.',
        ],
    ],
];

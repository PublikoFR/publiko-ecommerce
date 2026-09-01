<?php

declare(strict_types=1);

return [
    'nav' => 'E-mails',
    'model' => 'Modèle d\'e-mail',
    'model_plural' => 'Modèles d\'e-mails',
    'group' => 'Paramètres',

    'section' => [
        'settings' => 'Réglages',
        'content' => 'Contenu du message',
    ],

    'field' => [
        'key' => 'Identifiant technique',
        'name' => 'E-mail',
        'subject' => 'Objet',
        'enabled' => 'Actif',
        'enabled_help' => 'Décoché, cet e-mail n\'est jamais envoyé, même si l\'événement qui le déclenche se produit.',
        'blocks' => 'Blocs',
        'updated_at' => 'Modifié le',
    ],

    'block' => [
        'type' => 'Type de bloc',
        'paragraph' => 'Paragraphe',
        'heading' => 'Titre',
        'button' => 'Bouton',
        'divider' => 'Séparateur',
        'signature' => 'Signature',
        'text' => 'Texte',
        'label' => 'Libellé du bouton',
        'url' => 'Lien du bouton',
        'variant' => 'Style du bouton',
        'variant_primary' => 'Principal',
        'variant_accent' => 'Mise en avant',
    ],

    'hint' => [
        'available' => 'Variables utilisables : :list',
        'required' => 'obligatoires : :list',
    ],

    'error' => [
        'missing_required' => 'Variables obligatoires manquantes : :list. Sans elles, l\'e-mail perd sa fonction (lien de suivi, référence…).',
        'unknown_placeholder' => 'Variables inconnues : :list. Elles ne seront pas remplacées et s\'afficheront telles quelles.',
    ],
];

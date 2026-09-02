<?php

declare(strict_types=1);

return [
    'nav' => 'E-mails',
    'model' => 'Modèle d\'e-mail',
    'model_plural' => 'Modèles d\'e-mails',
    'group' => 'Configuration',

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
        'audience' => 'Destinataire',
        'test_recipient' => 'Envoyer à',
        'test_recipient_help' => 'Le message part avec des données d\'exemple et un objet préfixé [TEST].',
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

    'action' => [
        'save_settings' => 'Enregistrer les réglages',
        'preview' => 'Aperçu',
        'close' => 'Fermer',
        'send_test' => 'Envoyer un test',
    ],

    'test' => [
        'sent' => 'E-mail de test envoyé à :email',
        'failed' => 'Envoi du test impossible',
        'disabled' => 'Ce modèle est désactivé ou sans contenu : il n\'y a rien à envoyer.',
    ],

    'audience' => [
        'customer' => 'Client',
        'admin' => 'Équipe',
        'both' => 'Client + équipe',
    ],

    'preview' => [
        'disabled' => 'Ce modèle est désactivé ou sans contenu : aucun aperçu à afficher.',
        'error' => 'Impossible de composer l\'aperçu : :message',
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

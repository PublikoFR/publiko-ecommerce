<?php

return [
    'empty' => [
        'title' => 'Aucun média ou dossier trouvé',
    ],
    'folders' => [
        'title' => 'Medias',
        'single' => 'Dossier',
        'columns' => [
            'name' => 'Nom',
            'collection' => 'Collection',
            'description' => 'Description',
            'is_public' => 'Public',
            'has_user_access' => 'Accès utilisateur',
            'users' => 'Utilisateurs',
            'icon' => 'Icône',
            'color' => 'Couleur',
            'is_protected' => 'Protégé',
            'password' => 'Mot de passe',
            'password_confirmation' => 'Confirmation du mot de passe',
        ],
        'group' => 'Catalogue',
    ],
    'media' => [
        'title' => 'Médias',
        'single' => 'Média',
        'columns' => [
            'image' => 'Image',
            'model' => 'Modèle',
            'collection_name' => 'Nom de collection',
            'size' => 'Taille',
            'order_column' => 'Ordre',
        ],
        'actions' => [
            'sub_folder' => [
                'label' => 'Créer un sous-dossier',
            ],
            'create' => [
                'label' => 'Ajouter un média',
                'form' => [
                    'file' => 'Fichier',
                    'title' => 'Titre',
                    'description' => 'Description',
                ],
            ],
            'delete' => [
                'label' => 'Supprimer le dossier',
            ],
            'edit' => [
                'label' => 'Modifier le dossier',
            ],
        ],
        'notifications' => [
            'create-media' => 'Média créé avec succès',
            'delete-folder' => 'Dossier supprimé avec succès',
            'edit-folder' => 'Dossier modifié avec succès',
        ],
        'meta' => [
            'model' => 'Modèle',
            'file-name' => 'Nom du fichier',
            'type' => 'Type',
            'size' => 'Taille',
            'disk' => 'Disque',
            'url' => 'URL',
            'delete-media' => 'Supprimer le média',
        ],
    ],
];

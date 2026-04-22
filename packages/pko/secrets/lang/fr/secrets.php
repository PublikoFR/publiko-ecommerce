<?php

declare(strict_types=1);

return [
    'source' => [
        'label' => 'Source des credentials',
        'helper' => 'Choisissez où les clés API de ce module sont stockées.',
        'env' => 'Fichier .env (recommandé)',
        'db' => 'Base de données (chiffré)',
        'switched_to_env' => 'Source passée sur .env',
        'switched_to_db' => 'Source passée sur la base de données',
    ],
    'masked' => '••••••••',
    'missing' => 'Non renseignée',
    'saved' => 'Credentials enregistrés',
    'write_denied_env' => 'Impossible de modifier : le module est en mode .env. Basculez d\'abord sur "Base de données".',
];

<?php

use App\Providers\AppServiceProvider;

// Les providers Pko/* sont auto-découverts via extra.laravel.providers
// dans chaque packages/pko/<x>/composer.json (cf. composer.json racine
// → repositories[type=path] pour le symlink vendor/pko/*).
return [
    AppServiceProvider::class,
];

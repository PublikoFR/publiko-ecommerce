<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Taille maximale d'un fichier de la médiathèque (octets)
    |--------------------------------------------------------------------------
    | Remplace le plafond de 10 Mo de spatie/laravel-medialibrary : les notices
    | et brochures fournisseurs dépassent régulièrement 10 Mo. Appliqué à
    | `media-library.max_file_size` au boot du package.
    */
    'max_file_size' => (int) env('MEDIA_MAX_FILE_SIZE', 25 * 1024 * 1024),
];

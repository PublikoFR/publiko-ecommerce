<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TomatoPHP\FilamentMediaManager\Models\Folder;

class PkoMediaLibrarySeeder extends Seeder
{
    /**
     * Dossiers par défaut de la médiathèque. Crée les entrées manquantes sans
     * toucher aux dossiers déjà existants ni à leurs médias.
     *
     * @var array<int, array{name:string, collection:string, description?:?string}>
     */
    protected array $folders = [
        ['name' => 'Produits', 'collection' => 'products', 'description' => 'Visuels produits (fiches, galeries).'],
    ];

    public function run(): void
    {
        foreach ($this->folders as $data) {
            Folder::query()->firstOrCreate(
                ['collection' => $data['collection']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ],
            );
        }
    }
}

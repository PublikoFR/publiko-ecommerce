<?php

declare(strict_types=1);

namespace Pko\Storefront\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use Lunar\Models\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Importe un lot de pictogrammes sur les catégories racines du catalogue, en
 * associant chaque fichier à sa catégorie PAR SON NOM :
 * `portes-garage-fermetures-industrielles.svg` → « Portes de garage &
 * fermetures industrielles ».
 *
 * Pourquoi par nom : les identifiants de catégories diffèrent entre local, dev
 * et prod, un mapping par id ne se rejoue pas d'un environnement à l'autre.
 *
 * Règles :
 * - un fichier correspond à une catégorie racine dont le nom contient tous les
 *   mots significatifs du nom de fichier (articles et « et » ignorés) ; si
 *   plusieurs correspondent, la catégorie active la plus fournie gagne ;
 * - le picto devient la PREMIÈRE image de la catégorie (celle du menu latéral),
 *   les autres images sont conservées après lui ;
 * - relancer l'import remplace le picto du même nom de fichier (idempotent).
 */
class ImportCategoryIconsCommand extends Command
{
    protected $signature = 'storefront:import-category-icons
        {dir : Dossier contenant les pictos (svg, png, webp)}
        {--dry-run : Affiche les correspondances sans rien écrire}';

    protected $description = 'Attache des pictogrammes aux catégories racines en les associant par nom de fichier';

    private const STOPWORDS = ['de', 'des', 'du', 'd', 'et', 'la', 'le', 'les', 'l', 'en', 'a'];

    private const EXTENSIONS = ['svg', 'png', 'webp'];

    public function handle(): int
    {
        $dir = rtrim((string) $this->argument('dir'), '/');
        if (! is_dir($dir)) {
            $this->error("Dossier introuvable : {$dir}");

            return self::FAILURE;
        }

        $files = collect(scandir($dir) ?: [])
            ->filter(fn (string $f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
            ->values();

        if ($files->isEmpty()) {
            $this->warn('Aucun picto (svg, png, webp) dans ce dossier.');

            return self::FAILURE;
        }

        $roots = Collection::query()->whereNull('parent_id')->orderBy('_lft')->get();
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $unmatched = 0;

        foreach ($files as $file) {
            $collection = $this->match(pathinfo($file, PATHINFO_FILENAME), $roots);

            if (! $collection) {
                $unmatched++;
                $rows[] = [$file, '—', 'aucune catégorie'];

                continue;
            }

            $name = (string) $collection->translateAttribute('name');

            if (! $dryRun) {
                $this->attach($collection, $dir.'/'.$file, $file);
            }

            $rows[] = [$file, "#{$collection->id} {$name}".($collection->pko_enabled ? '' : ' (désactivée)'), $dryRun ? 'à importer' : 'importé'];
        }

        $this->table(['Fichier', 'Catégorie', 'Statut'], $rows);

        return $unmatched > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  SupportCollection<int, Collection>  $roots
     */
    private function match(string $fileName, SupportCollection $roots): ?Collection
    {
        $wanted = $this->tokens(str_replace(['-', '_'], ' ', $fileName));
        if ($wanted === []) {
            return null;
        }

        return $roots
            ->filter(fn (Collection $c) => array_diff($wanted, $this->tokens((string) $c->translateAttribute('name'))) === [])
            ->sortByDesc(fn (Collection $c) => [(int) (bool) $c->pko_enabled, (int) $c->_rgt - (int) $c->_lft])
            ->first();
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($value)));

        return array_values(array_diff(array_filter(explode(' ', $normalized)), self::STOPWORDS));
    }

    private function attach(Collection $collection, string $path, string $fileName): void
    {
        // Ré-import : on remplace le picto du même nom plutôt que d'empiler.
        $collection->getMedia('images')
            ->filter(fn (Media $m) => $m->file_name === $fileName)
            ->each(fn (Media $m) => $m->delete());

        $media = $collection->addMedia($path)
            ->preservingOriginal()
            ->usingFileName($fileName)
            ->toMediaCollection('images');

        $others = $collection->fresh()->getMedia('images')
            ->reject(fn (Media $m) => $m->id === $media->id)
            ->pluck('id')
            ->all();

        Media::setNewOrder([$media->id, ...$others]);
    }
}

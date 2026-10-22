<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Illuminate\Support\Str;
use Lunar\Models\Collection;

/**
 * Catégories (collections Lunar) réelles du site, lues en base à l'instant T.
 *
 * Remplace la liste de catégories figée dans le contexte IA des configs :
 * `expand()` substitue le marqueur {@see self::PLACEHOLDER} par les chemins
 * actuels (`Accueil>Parent>Enfant`, un par ligne) avant chaque parse, et
 * `findIdByPath()` fait le chemin inverse pour rattacher la réponse de l'IA.
 */
class CollectionCatalog
{
    public const PLACEHOLDER = '{{categories}}';

    /** Racine implicite du format de sortie des prompts (« Accueil>Motorisation>… »). */
    public const ROOT_LABEL = 'Accueil';

    /** @var array<int, array{id: int, parent_id: int|null, name: string, path: string}>|null */
    private static ?array $nodes = null;

    /** À appeler au début d'un parse : la liste est relue une fois par job. */
    public static function flush(): void
    {
        self::$nodes = null;
    }

    /**
     * @return array<int, string> Chemins complets, dans l'ordre de l'arbre.
     */
    public function paths(): array
    {
        return array_values(array_map(fn (array $n): string => $n['path'], $this->nodes()));
    }

    public function expand(string $context): string
    {
        if (! str_contains($context, self::PLACEHOLDER)) {
            return $context;
        }

        $paths = $this->paths();

        return str_replace(
            self::PLACEHOLDER,
            $paths === [] ? '(aucune catégorie définie sur le site)' : implode("\n", $paths),
            $context,
        );
    }

    /**
     * « Accueil>Motorisations>Moteurs » → id de la collection, en suivant les
     * noms de la racine vers la feuille (insensible à la casse et aux accents).
     */
    public function findIdByPath(string $path): ?int
    {
        $segments = array_values(array_filter(array_map('trim', explode('>', $path)), fn (string $s): bool => $s !== ''));
        if ($segments !== [] && self::normalize($segments[0]) === self::normalize(self::ROOT_LABEL)) {
            array_shift($segments);
        }
        if ($segments === []) {
            return null;
        }

        return $this->descend(null, array_map(self::normalize(...), $segments));
    }

    /**
     * Descente avec retour arrière : plusieurs racines (ou frères) peuvent porter
     * le même nom — ex. une catégorie de démo et celle du catalogue réel — et le
     * premier homonyme n'est pas forcément celui qui contient la suite du chemin.
     *
     * @param  array<int, string>  $segments  noms normalisés restant à descendre
     */
    private function descend(?int $parent, array $segments): ?int
    {
        $segment = array_shift($segments);

        foreach ($this->nodes() as $node) {
            if ($node['parent_id'] !== $parent || self::normalize($node['name']) !== $segment) {
                continue;
            }
            if ($segments === []) {
                return $node['id'];
            }
            if (($found = $this->descend($node['id'], $segments)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{id: int, parent_id: int|null, name: string, path: string}>
     */
    private function nodes(): array
    {
        if (self::$nodes !== null) {
            return self::$nodes;
        }

        $collections = Collection::query()->orderBy('_lft')->get();
        $byId = $collections->keyBy('id');

        $nodes = [];
        foreach ($collections as $collection) {
            $name = trim((string) $collection->translateAttribute('name'));
            if ($name === '') {
                continue;
            }

            $chain = [$name];
            for ($p = $collection->parent_id, $guard = 0; $p !== null && $byId->has($p) && $guard < 20; $guard++) {
                $parent = $byId[$p];
                array_unshift($chain, trim((string) $parent->translateAttribute('name')));
                $p = $parent->parent_id;
            }

            $nodes[(int) $collection->id] = [
                'id' => (int) $collection->id,
                'parent_id' => $collection->parent_id !== null ? (int) $collection->parent_id : null,
                'name' => $name,
                'path' => self::ROOT_LABEL.'>'.implode('>', $chain),
            ];
        }

        return self::$nodes = $nodes;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(Str::ascii($value));
    }
}

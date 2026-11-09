<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Support;

use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use Lunar\Models\Collection;

/**
 * Zones du schéma « À chaque installation, sa solution » (home, FEAT-009).
 *
 * Le schéma isométrique (storefront-cms::livewire.partials.home-solutions-scene)
 * dessine un équipement par zone ; chaque zone porte une pastille dont les
 * coordonnées (`x`, `y`) sont exprimées dans le viewBox du SVG — et non en %
 * par-dessus l'image comme dans la maquette, où les pastilles se décalaient
 * dès que la largeur changeait.
 *
 * Les zones sont fixes (elles correspondent au dessin). Chaque zone trouve sa
 * catégorie racine par son nom (AUTO_MATCH) ; l'admin (Page d'accueil →
 * Sections) peut imposer une autre collection, masquer la zone, changer libellé
 * et description : config('storefront.home.solutions').
 */
final class HomeSolutionZones
{
    public const VIEWBOX = '0 0 840 540';

    /**
     * Ordre = ordre d'affichage. `x`/`y` = point d'ancrage sur l'équipement.
     *
     * @return array<string, array{label: string, description: string, x: float, y: float}>
     */
    public static function all(): array
    {
        return [
            'gates' => ['label' => 'Portails', 'description' => 'Équipements et accessoires pour les entrées de site.', 'x' => 202.6, 'y' => 246.3],
            'garage' => ['label' => 'Garage & fermetures industrielles', 'description' => 'Solutions pour les accès de garage et les bâtiments professionnels.', 'x' => 439.6, 'y' => 278.7],
            'shutters' => ['label' => 'Volets & protections solaires', 'description' => 'Équipements des ouvertures et solutions de protection solaire.', 'x' => 214.3, 'y' => 141.4],
            'motors' => ['label' => 'Motorisations', 'description' => 'Motorisations pour volets, portails et portes de garage.', 'x' => 155.9, 'y' => 217.5],
            'intercom' => ['label' => 'Interphonie & visiophonie', 'description' => 'Identification et accueil des visiteurs.', 'x' => 240.0, 'y' => 263.4],
            'access' => ['label' => "Contrôle d'accès", 'description' => 'Équipements pour gérer les accès au site et au bâtiment.', 'x' => 534.7, 'y' => 351.6],
            'security' => ['label' => 'Alarme & vidéosurveillance', 'description' => 'Surveillance et protection des installations.', 'x' => 603.2, 'y' => 349.8],
            'smart' => ['label' => 'Domotique & connectivité', 'description' => 'Pilotage et connexion des équipements.', 'x' => 272.8, 'y' => 177.9],
            'supplies' => ['label' => 'Consommables', 'description' => 'Les compléments pour préparer et réaliser la pose.', 'x' => 353.8, 'y' => 276.0],
            'parts' => ['label' => 'Pièces détachées', 'description' => 'Recherche par équipement, type de pièce et fabricant.', 'x' => 522.2, 'y' => 387.6],
        ];
    }

    /**
     * Options du select admin (clé de zone => libellé par défaut).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (array $zone): string => $zone['label'], self::all());
    }

    /**
     * Entrées par défaut du repeater admin : une ligne par zone, collection en
     * automatique (rapprochement par nom, cf. AUTO_MATCH).
     *
     * @return list<array{zone: string, collection_id: null, label: null, description: null, hidden: false}>
     */
    public static function defaultEntries(): array
    {
        return array_map(
            fn (string $zone): array => ['zone' => $zone, 'collection_id' => null, 'label' => null, 'description' => null, 'hidden' => false],
            array_keys(self::all()),
        );
    }

    /**
     * Mots-clés de rapprochement automatique zone → catégorie racine, comparés au
     * nom normalisé de la collection (minuscules, sans accents ni ponctuation).
     *
     * Pourquoi : un mapping par identifiant ne survit pas d'un environnement à
     * l'autre (les ids de collections diffèrent entre local, dev et prod). Sans
     * ce repli, le module disparaissait tant que personne n'avait refait le
     * mapping en admin sur chaque serveur.
     *
     * @var array<string, list<string>>
     */
    public const AUTO_MATCH = [
        'gates' => ['portail'],
        'garage' => ['garage', 'fermetures industrielles'],
        'shutters' => ['volet'],
        'motors' => ['motorisation'],
        'intercom' => ['interphon', 'visiophon'],
        'access' => ['controle d acces'],
        'security' => ['alarme', 'videosurveillance'],
        'smart' => ['domotique'],
        'supplies' => ['consommable'],
        'parts' => ['pieces detachees', 'piece detachee'],
    ];

    /**
     * Zones affichables, numérotées 01, 02… dans l'ordre du schéma (la pastille
     * et la ligne de liste portent toujours le même numéro).
     *
     * Résolution d'une zone, par priorité :
     * 1. masquée en admin (`hidden`) → absente ;
     * 2. collection choisie en admin (`collection_id`) → celle-ci, si active ;
     * 3. sinon, catégorie racine active dont le nom contient un mot-clé de
     *    AUTO_MATCH (la plus fournie en sous-catégories si plusieurs
     *    correspondent). Une zone sans correspondance n'est pas affichée.
     *
     * @param  mixed  $entries  config('storefront.home.solutions')
     * @return list<array{key: string, number: string, label: string, description: string, url: string, x: float, y: float}>
     */
    public static function resolve(mixed $entries): array
    {
        $byZone = collect(is_array($entries) ? $entries : [])
            ->filter(fn ($e) => is_array($e) && isset(self::all()[$e['zone'] ?? '']))
            ->keyBy('zone');

        $explicitIds = $byZone->pluck('collection_id')->filter()->map(fn ($id) => (int) $id)->values()->all();

        /** @var SupportCollection<int, Collection> $candidates */
        $candidates = Collection::query()
            ->with('defaultUrl')
            ->where('pko_enabled', true)
            ->where(fn ($q) => $q->whereNull('parent_id')->orWhereIn('id', $explicitIds))
            ->orderBy('_lft')
            ->get();

        $byId = $candidates->keyBy('id');
        // À mots-clés égaux, la famille la plus fournie (plus grand sous-arbre
        // nested set) l'emporte sur une racine isolée — ex. une ancienne
        // catégorie de démo « Portails coulissants » face à « Portails ».
        $roots = $candidates->whereNull('parent_id')
            ->sortByDesc(fn (Collection $c) => (int) $c->_rgt - (int) $c->_lft)
            ->map(fn (Collection $c) => ['collection' => $c, 'name' => self::normalize((string) $c->translateAttribute('name'))]);

        $out = [];
        foreach (self::all() as $key => $zone) {
            $entry = $byZone->get($key, []);

            if (! empty($entry['hidden'])) {
                continue;
            }

            $collection = ! empty($entry['collection_id'])
                ? $byId->get((int) $entry['collection_id'])
                : $roots->first(fn (array $root) => self::matches($root['name'], self::AUTO_MATCH[$key] ?? []))['collection'] ?? null;

            $slug = $collection?->defaultUrl?->slug;
            if (! $collection || ! $slug) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'number' => str_pad((string) (count($out) + 1), 2, '0', STR_PAD_LEFT),
                'label' => trim((string) ($entry['label'] ?? '')) ?: $zone['label'],
                'description' => trim((string) ($entry['description'] ?? '')) ?: $zone['description'],
                'url' => route('collection.view', $slug),
                'x' => $zone['x'],
                'y' => $zone['y'],
            ];
        }

        return $out;
    }

    public static function normalize(string $value): string
    {
        $ascii = Str::ascii(mb_strtolower($value));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    /**
     * @param  list<string>  $keywords
     */
    private static function matches(string $name, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($name !== '' && str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }
}

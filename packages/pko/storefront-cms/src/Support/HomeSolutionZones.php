<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Support;

use Illuminate\Support\Collection as SupportCollection;
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
 * Les zones sont fixes (elles correspondent au dessin). Ce qui est éditable en
 * admin (Page d'accueil → Sections), c'est le lien zone → collection Lunar, le
 * libellé et la description : config('storefront.home.solutions').
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
     * Entrées par défaut du repeater admin : une ligne par zone, sans collection.
     *
     * @return list<array{zone: string, collection_id: null, label: null, description: null}>
     */
    public static function defaultEntries(): array
    {
        return array_map(
            fn (string $zone): array => ['zone' => $zone, 'collection_id' => null, 'label' => null, 'description' => null],
            array_keys(self::all()),
        );
    }

    /**
     * Zones affichables : celles reliées à une collection active ayant une URL.
     * Numérotées 01, 02… dans l'ordre du schéma, pour que la pastille et la
     * ligne de liste portent toujours le même numéro.
     *
     * @param  mixed  $entries  config('storefront.home.solutions')
     * @return list<array{key: string, number: string, label: string, description: string, url: string, x: float, y: float}>
     */
    public static function resolve(mixed $entries): array
    {
        $byZone = collect(is_array($entries) ? $entries : [])
            ->filter(fn ($e) => is_array($e) && isset(self::all()[$e['zone'] ?? '']) && ! empty($e['collection_id']))
            ->keyBy('zone');

        if ($byZone->isEmpty()) {
            return [];
        }

        /** @var SupportCollection<int, Collection> $collections */
        $collections = Collection::query()
            ->with('defaultUrl')
            ->whereIn('id', $byZone->pluck('collection_id')->map(fn ($id) => (int) $id)->all())
            ->where('pko_enabled', true)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach (self::all() as $key => $zone) {
            $entry = $byZone->get($key);
            $collection = $entry ? $collections->get((int) $entry['collection_id']) : null;
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
}

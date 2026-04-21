<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Support;

use Filament\Navigation\NavigationItem;

/**
 * Registre universel de sidebars on-page (affichées à droite).
 *
 * Usage :
 *   SideNavRegistry::register(
 *       key: 'expedition',
 *       matchRoutes: ['filament.lunar.resources.shipping-methods.*', ...],
 *       items: [NavigationItem::make(...), ...],
 *       heading: 'Expédition',
 *   );
 *
 * Puis le render hook `panels::page.end` enregistré dans AdminNavPlugin
 * rend la view `admin-nav::side-nav` qui consulte ce registre.
 */
class SideNavRegistry
{
    /**
     * @var array<string, array{matchRoutes: array<string>, items: array<NavigationItem>, heading: ?string}>
     */
    private static array $entries = [];

    /**
     * @param  array<string>  $matchRoutes  patterns `request()->routeIs()`
     * @param  array<NavigationItem>|callable  $items  liste directe ou closure renvoyant la liste
     */
    public static function register(
        string $key,
        array $matchRoutes,
        array|callable $items,
        ?string $heading = null,
    ): void {
        self::$entries[$key] = [
            'matchRoutes' => $matchRoutes,
            'items' => $items,
            'heading' => $heading,
        ];
    }

    /**
     * Renvoie l'entrée active (la 1re dont un pattern matche la route courante) ou null.
     *
     * @return array{items: array<NavigationItem>, heading: ?string}|null
     */
    public static function current(): ?array
    {
        $request = request();

        foreach (self::$entries as $entry) {
            foreach ($entry['matchRoutes'] as $pattern) {
                if ($request->routeIs($pattern)) {
                    $items = is_callable($entry['items']) ? ($entry['items'])() : $entry['items'];

                    return [
                        'items' => $items,
                        'heading' => $entry['heading'],
                    ];
                }
            }
        }

        return null;
    }
}

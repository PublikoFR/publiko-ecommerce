<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use App\Support\Ecotax;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Layout\Component as LayoutComponent;
use Filament\Tables\Columns\TextColumn;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\OrderLine;

/**
 * Lignes de la fiche commande admin : « 2 x 3,24 € » au lieu de « 2 @ 6,48 € ».
 *
 * Lunar affiche la quantité suivie du sous-total de la ligne derrière un « @ »,
 * ce qui se lit comme un prix unitaire alors que c'est quantité × prix. On affiche
 * le vrai prix unitaire.
 *
 * Sous ce montant : « dont éco-participation X € HT » pour la ligne, lu sur la
 * valeur FIGÉE à la commande (`OrderLine.meta.ecotax_cents`, cf. docs/ecotax.md),
 * jamais sur la variante actuelle. Rien si l'éco-taxe est nulle.
 */
final class OrderLinesTableExtension extends ResourceExtension
{
    /**
     * @param  array<int, Column|LayoutComponent>  $columns
     * @return array<int, Column|LayoutComponent>
     */
    public function extendOrderLinesTableColumns(array $columns): array
    {
        $unit = self::findColumn($columns, 'unit');

        $unit?->getStateUsing(fn (OrderLine $record): string => "{$record->quantity} x {$record->unit_price->formatted}");

        if ($unit instanceof TextColumn) {
            $unit->description(static function (OrderLine $record): ?string {
                $cents = Ecotax::forOrderLine($record);

                return $cents > 0
                    ? 'dont éco-participation '.Ecotax::format($cents).' HT'
                    : null;
            });
        }

        return $columns;
    }

    /**
     * @param  array<int, Column|LayoutComponent>  $components
     */
    private static function findColumn(array $components, string $name): ?Column
    {
        foreach ($components as $component) {
            if ($component instanceof Column && $component->getName() === $name) {
                return $component;
            }

            if ($component instanceof LayoutComponent) {
                $found = self::findColumn($component->getComponents(), $name);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}

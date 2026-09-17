<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Infolists\Components\Component;
use Filament\Infolists\Components\TextEntry;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;

/**
 * Affiche le nom du chantier (pko_site_name) sur la fiche commande Filament,
 * dans le résumé de commande, juste sous la référence.
 */
final class OrderSiteNameExtension extends ResourceExtension
{
    /**
     * @param  array<int, Component>  $schema
     * @return array<int, Component>
     */
    public function extendOrderSummarySchema(array $schema): array
    {
        $entry = TextEntry::make('pko_site_name')
            ->label('Chantier')
            ->alignEnd()
            ->visible(fn (?Order $record): bool => filled($record?->pko_site_name));

        $referenceIndex = null;
        foreach ($schema as $index => $component) {
            if ($component instanceof TextEntry && $component->getName() === 'reference') {
                $referenceIndex = $index;
                break;
            }
        }

        if ($referenceIndex === null) {
            $schema[] = $entry;

            return $schema;
        }

        array_splice($schema, $referenceIndex + 1, 0, [$entry]);

        return $schema;
    }
}

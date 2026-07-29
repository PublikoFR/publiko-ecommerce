<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages;

use Lunar\Admin\Filament\Resources\CustomerResource\Pages\EditCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource;

/**
 * Édition client : le formulaire devient le premier onglet du même groupe que
 * les relation managers (Commandes, Adresses, Utilisateurs, Prix négociés…),
 * au lieu d'être empilé au-dessus d'eux. Même parti pris que PkoViewCustomer.
 */
class PkoEditCustomer extends EditCustomer
{
    protected static string $resource = PkoCustomerResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Informations';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-identification';
    }
}

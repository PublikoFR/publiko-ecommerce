<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages;

use Lunar\Admin\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource;

/**
 * Fiche client : fusionne l'infolist et les relation managers (Commandes,
 * Adresses, Utilisateur) en un seul groupe d'onglets. Le premier onglet est le
 * contenu (infos client) — navigation plus pratique que le contenu au-dessus des
 * onglets. Filament active le contenu par défaut quand la fusion est activée.
 */
class PkoViewCustomer extends ViewCustomer
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

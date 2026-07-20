<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources;

use Lunar\Admin\Filament\Resources\CustomerResource;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoCreateCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoEditCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoListCustomers;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages\PkoViewCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\RelationManagers\NegotiatedPricesRelationManager;

/**
 * Swap de la CustomerResource de Lunar (via AppServiceProvider::swapLunarResources).
 * Objectif : PkoViewCustomer fusionne le contenu (infolist) et les relations
 * (Commandes / Adresses / Utilisateur) en un seul groupe d'onglets, avec les infos
 * client comme premier onglet — navigation plus pratique sur la fiche.
 *
 * Slug 'customers' conservé → les routes/URLs (et CustomerResource::getUrl() appelé
 * ailleurs) restent résolus. Les pages Pko redéclarent $resource pour éviter le
 * RouteNotFoundException (les pages Lunar hardcodent $resource = CustomerResource).
 */
class PkoCustomerResource extends CustomerResource
{
    protected static ?string $slug = 'customers';

    public static function getDefaultRelations(): array
    {
        return array_merge(parent::getDefaultRelations(), [
            NegotiatedPricesRelationManager::class,
        ]);
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => PkoListCustomers::route('/'),
            'create' => PkoCreateCustomer::route('/create'),
            'edit' => PkoEditCustomer::route('/{record}/edit'),
            'view' => PkoViewCustomer::route('/{record}'),
        ];
    }
}

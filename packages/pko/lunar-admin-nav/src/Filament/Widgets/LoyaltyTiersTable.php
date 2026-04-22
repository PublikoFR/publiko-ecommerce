<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Widgets;

use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Pko\Loyalty\Filament\Resources\LoyaltyTierResource;
use Pko\Loyalty\Models\LoyaltyTier;

class LoyaltyTiersTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return LoyaltyTierResource::table(
            $table
                ->query(LoyaltyTier::query())
                ->heading(null)
                ->headerActions([
                    Action::make('create')
                        ->label(__('admin-nav::admin.hubs.loyalty.actions.create_tier'))
                        ->icon('heroicon-m-plus')
                        ->url(LoyaltyTierResource::getUrl('create')),
                ])
        );
    }
}

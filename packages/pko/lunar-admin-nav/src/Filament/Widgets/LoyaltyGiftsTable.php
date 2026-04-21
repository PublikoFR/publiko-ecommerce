<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Pko\Loyalty\Filament\Resources\GiftHistoryResource;
use Pko\Loyalty\Models\GiftHistory;

class LoyaltyGiftsTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return GiftHistoryResource::table(
            $table->query(GiftHistory::query())->heading(null)
        );
    }
}

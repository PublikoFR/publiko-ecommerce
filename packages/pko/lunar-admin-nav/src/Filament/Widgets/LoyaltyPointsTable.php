<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Pko\Loyalty\Filament\Resources\PointsHistoryResource;
use Pko\Loyalty\Models\PointsHistory;

class LoyaltyPointsTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return PointsHistoryResource::table(
            $table->query(PointsHistory::query())->heading(null)
        );
    }
}

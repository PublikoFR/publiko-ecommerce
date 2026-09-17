<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Widgets;

use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Pko\AdminNav\Filament\Widgets\Concerns\ConfiguresResourceTableActions;
use Pko\StorefrontCms\Filament\Resources\HomeTileResource;
use Pko\StorefrontCms\Models\HomeTile;

class HomeTilesTable extends BaseWidget
{
    use ConfiguresResourceTableActions;

    protected int|string|array $columnSpan = 'full';

    protected function getTableResource(): string
    {
        return HomeTileResource::class;
    }

    public function table(Table $table): Table
    {
        return HomeTileResource::table(
            $table
                ->query(HomeTile::query())
                ->heading(null)
                ->headerActions([
                    Action::make('create')
                        ->label(__('admin-nav::admin.hubs.homepage.actions.create_tile'))
                        ->icon('heroicon-m-plus')
                        ->url(HomeTileResource::getUrl('index')),
                ])
        );
    }
}

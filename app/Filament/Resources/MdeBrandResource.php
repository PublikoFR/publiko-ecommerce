<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Admin\Filament\Resources\BrandResource\Pages\ManageBrandMedia;

class MdeBrandResource extends BrandResource
{
    public static function getDefaultPages(): array
    {
        $pages = parent::getDefaultPages();
        unset($pages['media']);

        return $pages;
    }

    public static function getDefaultSubNavigation(): array
    {
        return array_values(array_filter(
            parent::getDefaultSubNavigation(),
            fn (string $page) => $page !== ManageBrandMedia::class,
        ));
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns(static::getTableColumnsWithoutNativeMedia())
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->searchable();
    }

    protected static function getTableColumnsWithoutNativeMedia(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label(__('lunarpanel::brand.table.name.label'))
                ->searchable(),
            Tables\Columns\TextColumn::make('products_count')
                ->counts('products')
                ->formatStateUsing(fn ($state) => number_format((int) $state, 0))
                ->label(__('lunarpanel::brand.table.products_count.label')),
        ];
    }
}

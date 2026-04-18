<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Filament\Resources\FeatureFamilyResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Models\Collection as LunarCollection;

class CollectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'collections';

    protected static ?string $title = 'Catégories rattachées';

    protected static ?string $modelLabel = 'catégorie';

    protected static ?string $pluralModelLabel = 'catégories';

    public function form(Form $form): Form
    {
        return $form->schema([
            //
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('breadcrumb')
                    ->label('Chemin')
                    ->getStateUsing(function (LunarCollection $record): string {
                        $trail = $record->ancestors()
                            ->get()
                            ->map(fn (LunarCollection $c) => $c->translateAttribute('name') ?? (string) $c->id)
                            ->push($record->translateAttribute('name') ?? (string) $record->id)
                            ->filter()
                            ->values()
                            ->all();

                        return implode(' › ', $trail);
                    }),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelect(
                        fn (Select $select) => $select
                            ->getOptionLabelFromRecordUsing(
                                fn (LunarCollection $record) => $record->translateAttribute('name') ?? (string) $record->id,
                            )
                            ->searchable(['id']),
                    ),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->emptyStateHeading('Famille globale')
            ->emptyStateDescription('Laissez vide pour rendre la famille applicable à toutes les catégories.');
    }
}

<?php

declare(strict_types=1);

namespace Mde\CatalogFeatures\Filament\RelationManagers;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Models\Product;
use Mde\CatalogFeatures\Facades\Features;
use Mde\CatalogFeatures\Models\FeatureFamily;
use Mde\CatalogFeatures\Models\FeatureValue;

class ProductFeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'featureValues';

    protected static ?string $title = 'Caractéristiques';

    protected static ?string $modelLabel = 'caractéristique';

    protected static ?string $pluralModelLabel = 'caractéristiques';

    protected static ?string $icon = 'heroicon-o-tag';

    public function form(Form $form): Form
    {
        // Unused: creation/edit goes through the header action below,
        // which writes directly through the FeatureManager service.
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('family.name')
                    ->label('Famille')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Valeur')
                    ->searchable(),
                Tables\Columns\TextColumn::make('handle')
                    ->label('Handle')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultGroup('family.name')
            ->headerActions([
                Tables\Actions\Action::make('edit_features')
                    ->label('Éditer les caractéristiques')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Caractéristiques du produit')
                    ->modalSubmitActionLabel('Enregistrer')
                    ->form(fn () => $this->buildFamilySections())
                    ->fillForm(fn () => $this->currentSelection())
                    ->action(function (array $data): void {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();

                        $valueIds = [];
                        foreach ($data as $key => $value) {
                            if (! str_starts_with($key, 'family_')) {
                                continue;
                            }
                            if (is_array($value)) {
                                $valueIds = [...$valueIds, ...array_map('intval', $value)];
                            } elseif ($value !== null && $value !== '') {
                                $valueIds[] = (int) $value;
                            }
                        }

                        Features::sync($product, $valueIds);

                        Notification::make()
                            ->title('Caractéristiques mises à jour')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('detach')
                    ->label('Retirer')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (FeatureValue $record): void {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();
                        Features::detach($product, $record);
                    }),
            ])
            ->emptyStateHeading('Aucune caractéristique')
            ->emptyStateDescription('Cliquez sur « Éditer les caractéristiques » pour en ajouter.');
    }

    /**
     * @return array<int, Section>
     */
    protected function buildFamilySections(): array
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        $families = Features::familiesFor($product);

        return $families->map(function (FeatureFamily $family): Section {
            $field = $family->multi_value
                ? Select::make("family_{$family->id}")
                    ->label($family->name)
                    ->multiple()
                    ->options($family->values->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                : Select::make("family_{$family->id}")
                    ->label($family->name)
                    ->options($family->values->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload();

            return Section::make($family->name)
                ->description($family->multi_value ? 'Plusieurs valeurs autorisées.' : 'Une seule valeur.')
                ->schema([$field])
                ->collapsible();
        })->all();
    }

    /**
     * @return array<string, int|array<int>>
     */
    protected function currentSelection(): array
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        $grouped = Features::for($product);

        $out = [];
        foreach (Features::familiesFor($product) as $family) {
            $ids = ($grouped->get($family->id) ?? collect())->pluck('id')->map(fn ($id) => (int) $id)->all();
            $out["family_{$family->id}"] = $family->multi_value ? $ids : ($ids[0] ?? null);
        }

        return $out;
    }
}

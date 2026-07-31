<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Livewire;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Pko\ShippingCommon\Models\CarrierGridBracket;
use Pko\ShippingCommon\Models\CarrierService;

/**
 * Table CRUD de la grille tarifaire par poids d'un transporteur
 * (pko_carrier_grids). Les prix sont stockés en cents, saisis en euros.
 */
class CarrierGridTable extends AbstractCarrierTable
{
    public function table(Table $table): Table
    {
        return $table
            ->heading('Grille tarifaire par poids')
            ->description('Le prix appliqué est celui du premier palier dont le poids max ≥ poids du panier.')
            ->query(fn (): Builder => CarrierGridBracket::query()->where('carrier_code', $this->carrierCode))
            ->defaultSort('max_kg')
            ->paginated(false)
            ->groups([
                Group::make('service_code')
                    ->label('Service')
                    ->getTitleFromRecordUsing(fn (CarrierGridBracket $record): string => $record->service_code ?? 'Tous les services')
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('service_code')
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('max_kg')
                    ->label('Poids max')
                    ->fontFamily('mono')
                    ->suffix(' kg')
                    ->sortable(),
                TextColumn::make('price_cents')
                    ->label('Prix')
                    ->fontFamily('mono')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', ' ').' €')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter un palier')
                    ->icon('heroicon-m-plus')
                    ->modalHeading('Nouveau palier tarifaire')
                    ->form($this->formSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data = $this->toCents($data);
                        $data['carrier_code'] = $this->carrierCode;
                        $data['sort'] = (int) (CarrierGridBracket::query()
                            ->where('carrier_code', $this->carrierCode)
                            ->max('sort') ?? 0) + 10;

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifier')
                    ->modalHeading('Modifier le palier')
                    ->form($this->formSchema())
                    ->fillForm(fn (CarrierGridBracket $record): array => [
                        'service_code' => $record->service_code,
                        'max_kg' => $record->max_kg,
                        'price_eur' => $record->price_cents / 100,
                    ])
                    ->mutateFormDataUsing(fn (array $data): array => $this->toCents($data)),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Supprimer')
                    ->modalHeading('Supprimer le palier'),
            ])
            ->emptyStateHeading('Aucun palier tarifaire')
            ->emptyStateDescription('Ajoutez au moins un palier pour que ce transporteur puisse être tarifé au checkout.')
            ->emptyStateIcon('heroicon-o-table-cells');
    }

    /**
     * @return array<int, Component>
     */
    protected function formSchema(): array
    {
        return [
            Select::make('service_code')
                ->label('Service')
                ->helperText('Laisser vide pour appliquer le palier à tous les services du transporteur.')
                ->placeholder('Tous les services')
                ->options(fn (): array => CarrierService::query()
                    ->where('carrier_code', $this->carrierCode)
                    ->orderBy('sort')
                    ->pluck('label', 'service_code')
                    ->map(fn (string $label, string $code): string => "{$label} ({$code})")
                    ->all())
                ->searchable()
                ->native(false),
            TextInput::make('max_kg')
                ->label('Poids max')
                ->helperText('Palier appliqué jusqu\'à ce poids inclus.')
                ->numeric()
                ->minValue(1)
                ->suffix('kg')
                ->required(),
            TextInput::make('price_eur')
                ->label('Prix')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->suffix('€')
                ->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function toCents(array $data): array
    {
        $data['price_cents'] = (int) round(((float) ($data['price_eur'] ?? 0)) * 100);
        unset($data['price_eur']);

        $data['service_code'] = ! empty($data['service_code']) ? (string) $data['service_code'] : null;

        return $data;
    }
}

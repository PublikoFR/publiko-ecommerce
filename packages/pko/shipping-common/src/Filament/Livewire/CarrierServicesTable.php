<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Livewire;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;
use Pko\ShippingCommon\Models\CarrierService;

/**
 * Table CRUD des services d'un transporteur (pko_carrier_services).
 */
class CarrierServicesTable extends AbstractCarrierTable
{
    public function table(Table $table): Table
    {
        return $table
            ->heading('Services')
            ->description('Services proposés au checkout pour ce transporteur.')
            ->query(fn (): Builder => CarrierService::query()->where('carrier_code', $this->carrierCode))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('service_code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('label')
                    ->label('Libellé')
                    // Description affichée sous le libellé, en gris italique :
                    // la vue admin est étroite, une colonne dédiée la serrait trop.
                    ->description(fn (CarrierService $record): ?HtmlString => filled($record->description)
                        ? new HtmlString('<em>'.e((string) $record->description).'</em>')
                        : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $sub): Builder => $sub
                            ->where('label', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")))
                    ->wrap(),
                ToggleColumn::make('enabled')
                    ->label('Actif'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter un service')
                    ->icon('heroicon-m-plus')
                    ->modalHeading('Nouveau service')
                    ->form($this->formSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['carrier_code'] = $this->carrierCode;
                        $data['sort'] = (int) (CarrierService::query()
                            ->where('carrier_code', $this->carrierCode)
                            ->max('sort') ?? 0) + 10;

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifier')
                    ->modalHeading('Modifier le service')
                    ->form($this->formSchema()),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Supprimer')
                    ->modalHeading('Supprimer le service'),
            ])
            ->emptyStateHeading('Aucun service configuré')
            ->emptyStateDescription('Ajoutez les services que ce transporteur proposera au checkout.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    /**
     * @return array<int, Component>
     */
    protected function formSchema(): array
    {
        return [
            TextInput::make('service_code')
                ->label('Code')
                ->helperText('Identifiant technique renvoyé au checkout (ex : chrono13).')
                ->required()
                ->maxLength(64)
                ->unique(
                    table: CarrierService::class,
                    column: 'service_code',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('carrier_code', $this->carrierCode),
                ),
            TextInput::make('label')
                ->label('Libellé')
                ->helperText('Nom affiché au client.')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label('Description')
                ->rows(2)
                ->maxLength(500),
            Toggle::make('enabled')
                ->label('Actif')
                ->default(true),
        ];
    }
}

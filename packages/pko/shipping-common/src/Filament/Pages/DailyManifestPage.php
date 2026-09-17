<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Pages;

use Carbon\Exceptions\InvalidFormatException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\ShippingCommon\Filament\Clusters\Shipping;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Support\CarrierDisplayLabel;
use Pko\ShippingCommon\Support\ManifestPdf;

/**
 * Bordereau récapitulatif de remise (« bordereau du jour »).
 *
 * Équivalent de l'écran « Edition of the daily docket » du module PrestaShop
 * officiel : la liste des LT créées sur une journée, imprimée en double
 * exemplaire et signée à l'enlèvement — un exemplaire pour le chauffeur,
 * l'autre conservé comme preuve de prise en charge.
 *
 * Le bordereau est construit à partir de nos propres données
 * (`pko_carrier_shipments`) : aucun appel au transporteur n'est nécessaire.
 */
class DailyManifestPage extends BasePage implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = Shipping::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'pko-shipping-common::pages.daily-manifest';

    protected static ?string $slug = 'bordereau';

    public static function getNavigationLabel(): string
    {
        return 'Bordereau de remise';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Bordereau de remise';
    }

    public function getSubheading(): ?string
    {
        return 'Récapitulatif des colis remis au transporteur. À imprimer en deux exemplaires et à faire signer par le chauffeur.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_today')
                ->label('Bordereau du jour')
                ->icon('heroicon-o-printer')
                ->action(fn () => $this->streamManifest(
                    $this->shipmentsForDate(Carbon::today()),
                    Carbon::today(),
                )),
        ];
    }

    /**
     * Journée ciblée par l'URL (`?date=YYYY-MM-DD`), aujourd'hui à défaut.
     * Une valeur illisible est ignorée plutôt que de faire planter la page.
     */
    protected function requestedDate(): Carbon
    {
        $raw = request()->query('date');

        if (! is_string($raw) || $raw === '') {
            return Carbon::today();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (InvalidFormatException) {
            return Carbon::today();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CarrierShipment::query()
                    ->where('status', CarrierShipment::STATUS_CREATED)
                    ->whereNotNull('tracking_number')
            )
            ->columns([
                TextColumn::make('tracking_number')
                    ->label('N° de LT')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('order_id')
                    ->label('Commande')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->searchable(),
                TextColumn::make('carrier')
                    ->label('Transporteur')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => CarrierDisplayLabel::carrier($state)),
                TextColumn::make('service_code')
                    ->label('Service')
                    ->formatStateUsing(fn (?string $state, CarrierShipment $record): string => CarrierDisplayLabel::service($record->carrier, $state)),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_on')
                    ->form([
                        DatePicker::make('date')
                            ->label('Date de remise')
                            // Le défaut est lu sur la requête pour que le raccourci
                            // « Bordereau du … » de la fiche commande ouvre directement
                            // la bonne journée. Le format `tableFilters[...]` de Filament
                            // ne survit pas au défaut du filtre lors du premier rendu.
                            ->default($this->requestedDate())
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['date'] ?? null,
                        fn (Builder $q, $date): Builder => $q->whereDate('created_at', $date),
                    ))
                    ->indicateUsing(function (array $data): ?string {
                        $date = $data['date'] ?? null;

                        return $date ? 'Remise du '.Carbon::parse($date)->format('d/m/Y') : null;
                    }),
                SelectFilter::make('carrier')
                    ->label('Transporteur')
                    ->options([
                        'chronopost' => 'Chronopost',
                        'colissimo' => 'Colissimo',
                    ]),
            ])
            ->bulkActions([
                BulkAction::make('print_manifest')
                    ->label('Éditer le bordereau')
                    ->icon('heroicon-o-printer')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => $this->streamManifest($records, Carbon::today())),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aucun colis remis')
            ->emptyStateDescription('Les envois apparaissent ici dès que leur étiquette a été générée.');
    }

    /**
     * @return Collection<int, CarrierShipment>
     */
    protected function shipmentsForDate(Carbon $date): Collection
    {
        return CarrierShipment::query()
            ->where('status', CarrierShipment::STATUS_CREATED)
            ->whereNotNull('tracking_number')
            ->whereDate('created_at', $date)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  Collection<int, CarrierShipment>  $shipments
     */
    protected function streamManifest(Collection $shipments, Carbon $date)
    {
        if ($shipments->isEmpty()) {
            Notification::make()
                ->title('Aucun colis à porter au bordereau')
                ->warning()
                ->send();

            return null;
        }

        return ManifestPdf::download($shipments, $date);
    }
}

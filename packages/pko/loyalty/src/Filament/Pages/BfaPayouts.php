<?php

declare(strict_types=1);

namespace Pko\Loyalty\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Lunar\Admin\Support\Pages\BasePage;
use Pko\Loyalty\Models\BfaPayout;
use Pko\Loyalty\Services\BfaCalculator;
use Pko\Loyalty\Services\BfaPayoutEmitter;
use Pko\Loyalty\Support\Money;

/**
 * Pré-calcul de fin d'année du BFA et émission des avoirs.
 *
 * Une ligne par tranche (déblocage d'un palier BFA). Le montant est ajustable
 * tant que l'avoir n'est pas émis ; l'émission est idempotente et suivie
 * ligne par ligne (cf. `BfaPayoutEmitter`).
 */
class BfaPayouts extends BasePage implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?int $navigationSort = 54;

    protected static ?string $slug = 'fidelite/bfa';

    protected static string $view = 'loyalty::filament.pages.bfa-payouts';

    public static function getNavigationLabel(): string
    {
        return __('loyalty::admin.bfa.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('loyalty::admin.bfa.title');
    }

    public function getSubheading(): ?string
    {
        return __('loyalty::admin.bfa.subheading');
    }

    public function selectedYear(): int
    {
        $value = $this->getTableFilterState('year')['value'] ?? null;

        return $value !== null && $value !== '' ? (int) $value : self::defaultYear();
    }

    /** En janvier, on clôture l'année écoulée. */
    public static function defaultYear(): int
    {
        return (int) now()->year - 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('precompute')
                ->label(fn (): string => __('loyalty::admin.bfa.precompute', ['year' => $this->selectedYear()]))
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->action(function (): void {
                    $count = app(BfaCalculator::class)->precompute($this->selectedYear());

                    Notification::make()
                        ->success()
                        ->title(__('loyalty::admin.bfa.precomputed', ['count' => $count]))
                        ->send();
                }),

            Action::make('emitAll')
                ->label(fn (): string => __('loyalty::admin.bfa.emit_all', ['year' => $this->selectedYear()]))
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalDescription(__('loyalty::admin.bfa.emit_confirm'))
                ->disabled(fn (): bool => ! BfaPayoutEmitter::canIssueYear($this->selectedYear()))
                ->tooltip(fn (): ?string => BfaPayoutEmitter::canIssueYear($this->selectedYear()) ? null : __('loyalty::admin.bfa.year_open'))
                ->action(fn () => $this->notifyEmission(app(BfaPayoutEmitter::class)->emitYear($this->selectedYear()))),
        ];
    }

    public function table(Table $table): Table
    {
        $years = range((int) now()->year, (int) now()->year - 5);

        return $table
            ->query(BfaPayout::query()->with('customer'))
            ->defaultSort('customer_id')
            ->columns([
                TextColumn::make('customer.company_name')
                    ->label(__('loyalty::admin.bfa.columns.customer'))
                    ->formatStateUsing(fn ($state, BfaPayout $record): string => self::customerName($record))
                    ->default('—')
                    ->searchable(['company_name', 'first_name', 'last_name']),
                TextColumn::make('tier_name')->label(__('loyalty::admin.bfa.columns.tier')),
                TextColumn::make('period_start')
                    ->label(__('loyalty::admin.bfa.columns.period'))
                    ->formatStateUsing(fn (BfaPayout $record): string => $record->period_start->format('d/m/Y').' → '.$record->period_end->format('d/m/Y')),
                TextColumn::make('revenue_ht')
                    ->label(__('loyalty::admin.bfa.columns.revenue'))
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state))
                    ->alignEnd(),
                TextColumn::make('computed_amount')
                    ->label(__('loyalty::admin.bfa.columns.computed'))
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state))
                    ->description(fn (BfaPayout $record): string => self::formula($record))
                    ->alignEnd(),
                TextColumn::make('adjusted_amount')
                    ->label(__('loyalty::admin.bfa.columns.to_issue'))
                    ->state(fn (BfaPayout $record): string => Money::format($record->amountToIssue()))
                    ->description(fn (BfaPayout $record): ?string => $record->adjusted_amount !== null ? __('loyalty::admin.bfa.adjusted') : null)
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('loyalty::admin.bfa.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BfaPayout::statusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        BfaPayout::STATUS_ISSUED => 'success',
                        BfaPayout::STATUS_FAILED => 'danger',
                        BfaPayout::STATUS_ISSUING => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (BfaPayout $record): ?string => $record->last_error),
                TextColumn::make('credit_note_number')
                    ->label(__('loyalty::admin.bfa.columns.credit_note'))
                    ->placeholder('—')
                    ->copyable(),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('loyalty::admin.bfa.columns.year'))
                    ->options(array_combine($years, $years))
                    ->default((string) self::defaultYear())
                    ->selectablePlaceholder(false),
                SelectFilter::make('status')
                    ->label(__('loyalty::admin.bfa.columns.status'))
                    ->options(collect([BfaPayout::STATUS_DRAFT, BfaPayout::STATUS_ISSUING, BfaPayout::STATUS_ISSUED, BfaPayout::STATUS_FAILED])
                        ->mapWithKeys(fn (string $s) => [$s => BfaPayout::statusLabel($s)])
                        ->all()),
            ])
            ->actions([
                TableAction::make('adjust')
                    ->label(__('loyalty::admin.bfa.adjust'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (BfaPayout $record): bool => $record->isAmountEditable())
                    ->fillForm(fn (BfaPayout $record): array => [
                        'amount' => number_format($record->amountToIssue() / 100, 2, ',', ''),
                    ])
                    ->form([
                        TextInput::make('amount')
                            ->label(__('loyalty::admin.bfa.amount_eur'))
                            ->helperText(__('loyalty::admin.bfa.amount_help'))
                            // Texte + clavier décimal : `numeric()` refuse la virgule.
                            ->inputMode('decimal')
                            ->required()
                            ->regex('/^\d+([.,]\d{1,2})?$/')
                            ->suffix('€ HT'),
                    ])
                    ->action(function (BfaPayout $record, array $data): void {
                        $record->refresh();
                        if (! $record->isAmountEditable()) {
                            Notification::make()->danger()->title(__('loyalty::admin.bfa.amount_locked'))->send();

                            return;
                        }

                        $cents = self::toCents((string) $data['amount']);

                        // Montant égal au calcul : on retombe sur le pré-calcul.
                        $record->update([
                            'adjusted_amount' => $cents === (int) $record->computed_amount ? null : $cents,
                        ]);
                    }),
                TableAction::make('emit')
                    ->label(__('loyalty::admin.bfa.emit'))
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (BfaPayout $record): bool => $record->isEditable() && BfaPayoutEmitter::canIssueYear((int) $record->year))
                    ->action(function (BfaPayout $record): void {
                        $result = app(BfaPayoutEmitter::class)->emit($record);
                        $this->notifyEmission([$result => 1]);
                    }),
            ])
            ->bulkActions([
                BulkAction::make('emitSelected')
                    ->label(__('loyalty::admin.bfa.emit_selected'))
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $emitter = app(BfaPayoutEmitter::class);
                        $counts = [];
                        foreach ($records as $record) {
                            $result = $emitter->emit($record);
                            $counts[$result] = ($counts[$result] ?? 0) + 1;
                        }
                        $this->notifyEmission($counts);
                    }),
            ])
            ->emptyStateHeading(__('loyalty::admin.bfa.empty'))
            ->emptyStateDescription(__('loyalty::admin.bfa.empty_help'));
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function notifyEmission(array $counts): void
    {
        $failed = (int) ($counts[BfaPayoutEmitter::RESULT_FAILED] ?? 0);

        Notification::make()
            ->{$failed > 0 ? 'danger' : 'success'}()
            ->title(__('loyalty::admin.bfa.emitted', [
                'issued' => (int) ($counts[BfaPayoutEmitter::RESULT_ISSUED] ?? 0),
                'skipped' => (int) ($counts[BfaPayoutEmitter::RESULT_SKIPPED] ?? 0),
                'failed' => $failed,
            ]))
            ->send();
    }

    /** « 72,5 » / « 72.50 » → 7250 (sans float). */
    public static function toCents(string $euros): int
    {
        [$int, $dec] = explode('.', str_replace(',', '.', trim($euros))) + [1 => ''];

        return (int) $int * 100 + (int) str_pad(substr($dec, 0, 2), 2, '0');
    }

    private static function customerName(BfaPayout $record): string
    {
        $customer = $record->customer;
        if ($customer === null) {
            return '—';
        }

        return (string) ($customer->company_name ?: trim($customer->first_name.' '.$customer->last_name));
    }

    /** « 100 € + 2 % de 5 000 € » */
    private static function formula(BfaPayout $record): string
    {
        $percent = rtrim(rtrim(str_replace('.', ',', (string) $record->percent), '0'), ',');

        return Money::format((int) $record->fixed_amount).' + '.$percent.' % de '.Money::format((int) $record->revenue_ht);
    }
}

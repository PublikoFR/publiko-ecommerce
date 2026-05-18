<?php

declare(strict_types=1);

namespace Pko\Pennylane\Filament\Resources;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Pko\Pennylane\Filament\Clusters\PennylaneCluster;
use Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource\Pages;
use Pko\Pennylane\Jobs\SyncOrderInvoiceJob;
use Pko\Pennylane\Models\PennylaneInvoice;

class PennylaneInvoiceResource extends Resource
{
    protected static ?string $model = PennylaneInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $cluster = PennylaneCluster::class;

    public static function getNavigationLabel(): string
    {
        return __('pko-pennylane::admin.invoice.nav');
    }

    public static function getModelLabel(): string
    {
        return __('pko-pennylane::admin.invoice.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pko-pennylane::admin.invoice.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Placeholder::make('pennylane_invoice_number')
                ->label(__('pko-pennylane::admin.invoice.fields.pennylane_invoice_number'))
                ->content(fn (?PennylaneInvoice $record) => $record?->pennylane_invoice_number ?? '—'),
            Placeholder::make('order_id')
                ->label(__('pko-pennylane::admin.invoice.fields.order_id'))
                ->content(fn (?PennylaneInvoice $record) => $record?->order_id ?? '—'),
            Placeholder::make('type')
                ->label(__('pko-pennylane::admin.invoice.fields.type'))
                ->content(fn (?PennylaneInvoice $record) => $record
                    ? __('pko-pennylane::admin.invoice.types.'.$record->type)
                    : '—'),
            Placeholder::make('status')
                ->label(__('pko-pennylane::admin.invoice.fields.status'))
                ->content(fn (?PennylaneInvoice $record) => $record
                    ? __('pko-pennylane::admin.invoice.statuses.'.$record->status)
                    : '—'),
            Placeholder::make('external_reference')
                ->label(__('pko-pennylane::admin.invoice.fields.external_reference'))
                ->content(fn (?PennylaneInvoice $record) => $record?->external_reference ?? '—'),
            Textarea::make('last_error')
                ->label(__('pko-pennylane::admin.invoice.fields.last_error'))
                ->disabled()
                ->rows(6)
                ->visible(fn (?PennylaneInvoice $record) => ! empty($record?->last_error)),
            KeyValue::make('payload_snapshot')
                ->label('Payload')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pennylane_invoice_number')
                    ->label(__('pko-pennylane::admin.invoice.fields.pennylane_invoice_number'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('order_id')
                    ->label(__('pko-pennylane::admin.invoice.fields.order_id'))
                    ->sortable(),
                BadgeColumn::make('type')
                    ->label(__('pko-pennylane::admin.invoice.fields.type'))
                    ->formatStateUsing(fn (string $state) => __('pko-pennylane::admin.invoice.types.'.$state))
                    ->colors([
                        'primary' => PennylaneInvoice::TYPE_INVOICE,
                        'warning' => PennylaneInvoice::TYPE_CREDIT_NOTE,
                    ]),
                BadgeColumn::make('status')
                    ->label(__('pko-pennylane::admin.invoice.fields.status'))
                    ->formatStateUsing(fn (string $state) => __('pko-pennylane::admin.invoice.statuses.'.$state))
                    ->colors([
                        'gray' => PennylaneInvoice::STATUS_PENDING,
                        'warning' => PennylaneInvoice::STATUS_DRAFT,
                        'success' => PennylaneInvoice::STATUS_FINALIZED,
                        'danger' => PennylaneInvoice::STATUS_FAILED,
                    ]),
                TextColumn::make('synced_at')
                    ->label(__('pko-pennylane::admin.invoice.fields.synced_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        PennylaneInvoice::TYPE_INVOICE => __('pko-pennylane::admin.invoice.types.invoice'),
                        PennylaneInvoice::TYPE_CREDIT_NOTE => __('pko-pennylane::admin.invoice.types.credit_note'),
                    ]),
                SelectFilter::make('status')
                    ->options([
                        PennylaneInvoice::STATUS_PENDING => __('pko-pennylane::admin.invoice.statuses.draft'),
                        PennylaneInvoice::STATUS_DRAFT => __('pko-pennylane::admin.invoice.statuses.draft'),
                        PennylaneInvoice::STATUS_FINALIZED => __('pko-pennylane::admin.invoice.statuses.finalized'),
                        PennylaneInvoice::STATUS_FAILED => __('pko-pennylane::admin.invoice.statuses.failed'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('resync')
                    ->label(__('pko-pennylane::admin.invoice.actions.resync'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PennylaneInvoice $record) => $record->type === PennylaneInvoice::TYPE_INVOICE
                        && $record->order_id)
                    ->action(function (PennylaneInvoice $record): void {
                        SyncOrderInvoiceJob::dispatch((int) $record->order_id);
                    })
                    ->successNotificationTitle(__('pko-pennylane::admin.invoice.actions.resync_success')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPennylaneInvoices::route('/'),
            'view' => Pages\ViewPennylaneInvoice::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

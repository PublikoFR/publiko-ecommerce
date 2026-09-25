<?php

declare(strict_types=1);

namespace Pko\Pennylane\Filament\Resources;

use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Lunar\Admin\Filament\Resources\OrderResource;
use Pko\Pennylane\Filament\Clusters\PennylaneCluster;
use Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource\Pages;
use Pko\Pennylane\Jobs\SyncOrderInvoiceJob;
use Pko\Pennylane\Models\PennylaneInvoice;
use Pko\Pennylane\Support\InvoicePayload;

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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns(3)
            ->schema([
                Group::make([
                    Section::make(__('pko-pennylane::admin.invoice.sections.error'))
                        ->icon('heroicon-o-exclamation-triangle')
                        ->iconColor('danger')
                        ->visible(fn (PennylaneInvoice $record): bool => filled($record->last_error))
                        ->schema([
                            TextEntry::make('last_error')
                                ->hiddenLabel()
                                ->fontFamily(FontFamily::Mono)
                                ->color('danger'),
                        ]),
                    Section::make(__('pko-pennylane::admin.invoice.sections.lines'))
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            ViewEntry::make('payload_snapshot')
                                ->hiddenLabel()
                                ->view('pko-pennylane::infolists.invoice-lines')
                                ->state(fn (PennylaneInvoice $record): InvoicePayload => InvoicePayload::from($record->payload_snapshot)),
                        ]),
                    Section::make(__('pko-pennylane::admin.invoice.sections.payload'))
                        ->icon('heroicon-o-code-bracket')
                        ->description(__('pko-pennylane::admin.invoice.sections.payload_help'))
                        ->collapsed()
                        ->schema([
                            ViewEntry::make('payload_json')
                                ->hiddenLabel()
                                ->view('pko-pennylane::infolists.payload-json')
                                ->state(fn (PennylaneInvoice $record): string => (string) json_encode(
                                    InvoicePayload::from($record->payload_snapshot)->toArray(),
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                                )),
                        ]),
                ])->columnSpan(['default' => 3, 'lg' => 2]),

                Group::make([
                    Section::make(__('pko-pennylane::admin.invoice.sections.document'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            TextEntry::make('pennylane_invoice_number')
                                ->label(__('pko-pennylane::admin.invoice.fields.pennylane_invoice_number'))
                                ->size(TextEntrySize::Large)
                                ->weight(FontWeight::Bold)
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('type')
                                ->label(__('pko-pennylane::admin.invoice.fields.type'))
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => __('pko-pennylane::admin.invoice.types.'.$state))
                                ->color(fn (string $state): string => $state === PennylaneInvoice::TYPE_CREDIT_NOTE ? 'warning' : 'primary'),
                            TextEntry::make('status')
                                ->label(__('pko-pennylane::admin.invoice.fields.status'))
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => __('pko-pennylane::admin.invoice.statuses.'.$state))
                                ->color(fn (string $state): string => self::statusColor($state))
                                ->icon(fn (string $state): string => self::statusIcon($state)),
                            TextEntry::make('invoice_date')
                                ->label(__('pko-pennylane::admin.invoice.fields.date'))
                                ->state(fn (PennylaneInvoice $record): mixed => InvoicePayload::from($record->payload_snapshot)->get('date'))
                                ->date('d/m/Y')
                                ->placeholder('—'),
                            TextEntry::make('deadline')
                                ->label(__('pko-pennylane::admin.invoice.fields.deadline'))
                                ->state(fn (PennylaneInvoice $record): mixed => InvoicePayload::from($record->payload_snapshot)->get('deadline'))
                                ->date('d/m/Y')
                                ->placeholder('—'),
                            TextEntry::make('total')
                                ->label(__('pko-pennylane::admin.invoice.fields.total'))
                                ->state(fn (PennylaneInvoice $record): string => self::formattedTotal($record))
                                ->weight(FontWeight::SemiBold),
                        ]),
                    Section::make(__('pko-pennylane::admin.invoice.sections.links'))
                        ->icon('heroicon-o-link')
                        ->schema([
                            TextEntry::make('order.reference')
                                ->label(__('pko-pennylane::admin.invoice.fields.order_id'))
                                ->state(fn (PennylaneInvoice $record): ?string => $record->order
                                    ? ($record->order->reference ?: '#'.$record->order_id)
                                    : null)
                                ->url(fn (PennylaneInvoice $record): ?string => $record->order_id
                                    ? OrderResource::getUrl('order', ['record' => $record->order_id])
                                    : null)
                                ->color('primary')
                                ->icon('heroicon-o-shopping-bag')
                                ->placeholder('—'),
                            TextEntry::make('customer')
                                ->label(__('pko-pennylane::admin.invoice.fields.customer'))
                                ->state(fn (PennylaneInvoice $record): ?string => self::customerName($record))
                                ->placeholder('—'),
                            TextEntry::make('parent.pennylane_invoice_number')
                                ->label(__('pko-pennylane::admin.invoice.fields.parent'))
                                ->url(fn (PennylaneInvoice $record): ?string => $record->parent_invoice_id
                                    ? self::getUrl('view', ['record' => $record->parent_invoice_id])
                                    : null)
                                ->color('primary')
                                ->visible(fn (PennylaneInvoice $record): bool => $record->parent_invoice_id !== null),
                            TextEntry::make('external_reference')
                                ->label(__('pko-pennylane::admin.invoice.fields.external_reference'))
                                ->fontFamily(FontFamily::Mono)
                                ->copyable(),
                            TextEntry::make('pennylane_id')
                                ->label(__('pko-pennylane::admin.invoice.fields.pennylane_id'))
                                ->fontFamily(FontFamily::Mono)
                                ->copyable()
                                ->placeholder('—'),
                        ]),
                    Section::make(__('pko-pennylane::admin.invoice.sections.tracking'))
                        ->icon('heroicon-o-clock')
                        ->schema([
                            TextEntry::make('synced_at')
                                ->label(__('pko-pennylane::admin.invoice.fields.synced_at'))
                                ->dateTime('d/m/Y H:i')
                                ->placeholder('—'),
                            TextEntry::make('emailed_at')
                                ->label(__('pko-pennylane::admin.invoice.fields.emailed_at'))
                                ->dateTime('d/m/Y H:i')
                                ->placeholder(__('pko-pennylane::admin.invoice.not_emailed')),
                            TextEntry::make('created_at')
                                ->label(__('pko-pennylane::admin.invoice.fields.created_at'))
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ])->columnSpan(['default' => 3, 'lg' => 1]),
            ]);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            PennylaneInvoice::STATUS_FINALIZED => 'success',
            PennylaneInvoice::STATUS_DRAFT => 'warning',
            PennylaneInvoice::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    private static function statusIcon(string $status): string
    {
        return match ($status) {
            PennylaneInvoice::STATUS_FINALIZED => 'heroicon-m-check-circle',
            PennylaneInvoice::STATUS_FAILED => 'heroicon-m-x-circle',
            default => 'heroicon-m-clock',
        };
    }

    public static function formattedTotal(PennylaneInvoice $record): string
    {
        $payload = InvoicePayload::from($record->payload_snapshot);

        return InvoicePayload::money($payload->totals()['total'], $payload->currency()).' TTC';
    }

    private static function customerName(PennylaneInvoice $record): ?string
    {
        $address = $record->order?->billingAddress;
        if (! $address) {
            return null;
        }

        $name = trim($address->first_name.' '.$address->last_name);

        return $address->company_name ? trim($address->company_name.($name !== '' ? ' — '.$name : '')) : ($name ?: null);
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

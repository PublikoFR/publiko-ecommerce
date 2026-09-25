<?php

declare(strict_types=1);

namespace Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource;
use Pko\Pennylane\Jobs\SyncOrderInvoiceJob;
use Pko\Pennylane\Jobs\SyncRefundCreditNoteJob;
use Pko\Pennylane\Models\PennylaneInvoice;
use Pko\Pennylane\Services\OrderDocuments;

/**
 * @property PennylaneInvoice $record
 */
class ViewPennylaneInvoice extends ViewRecord
{
    protected static string $resource = PennylaneInvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        return trim(__('pko-pennylane::admin.invoice.types.'.$this->record->type).' '.($this->record->pennylane_invoice_number ?? ''));
    }

    public function getSubheading(): string|Htmlable|null
    {
        return PennylaneInvoiceResource::formattedTotal($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resync')
                ->label(__('pko-pennylane::admin.invoice.actions.resync'))
                ->icon('heroicon-o-arrow-path')
                ->link()
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->canResync())
                ->action(function (): void {
                    $this->record->type === PennylaneInvoice::TYPE_CREDIT_NOTE
                        ? SyncRefundCreditNoteJob::dispatch((int) $this->record->transaction_id)
                        : SyncOrderInvoiceJob::dispatch((int) $this->record->order_id);
                })
                ->successNotificationTitle(__('pko-pennylane::admin.invoice.actions.resync_success')),
            Action::make('open_pennylane')
                ->label(__('pko-pennylane::admin.invoice.actions.open_pennylane'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): ?string => $this->record->pennylaneAppUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->record->pennylaneAppUrl() !== null),
            Action::make('view_pdf')
                ->label(__('pko-pennylane::admin.invoice.actions.view_pdf'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): ?string => OrderDocuments::pdfUrl($this->record, inline: true), shouldOpenInNewTab: true)
                ->visible(fn (): bool => OrderDocuments::pdfUrl($this->record) !== null),
            Action::make('download_pdf')
                ->label(__('pko-pennylane::admin.invoice.actions.download_pdf'))
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): ?string => OrderDocuments::pdfUrl($this->record))
                ->visible(fn (): bool => OrderDocuments::pdfUrl($this->record) !== null),
        ];
    }

    private function canResync(): bool
    {
        return $this->record->type === PennylaneInvoice::TYPE_CREDIT_NOTE
            ? $this->record->transaction_id !== null
            : $this->record->order_id !== null;
    }
}

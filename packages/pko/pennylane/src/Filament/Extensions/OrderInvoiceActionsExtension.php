<?php

declare(strict_types=1);

namespace Pko\Pennylane\Filament\Extensions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\URL;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Pennylane\Jobs\SyncOrderInvoiceJob;
use Pko\Pennylane\Jobs\SyncRefundCreditNoteJob;
use Pko\Pennylane\Models\PennylaneInvoice;

/**
 * Hide Lunar's native "Télécharger le PDF" action on the order page and replace it
 * with Pennylane-sourced invoice + credit note downloads. Renders a disabled badge
 * while the sync is pending, and a modal with the last error + "Retry" when failed.
 */
final class OrderInvoiceActionsExtension extends ResourceExtension
{
    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<int, Action|ActionGroup>
     */
    public function headerActions(array $actions): array
    {
        $actions = array_values(array_filter(
            $actions,
            fn ($action) => ! (method_exists($action, 'getName') && $action->getName() === 'download_pdf'),
        ));

        $actions[] = $this->invoiceAction();

        $creditNotesAction = $this->creditNotesAction();
        if ($creditNotesAction !== null) {
            $actions[] = $creditNotesAction;
        }

        return $actions;
    }

    private function invoiceAction(): Action
    {
        return Action::make('pennylane_invoice')
            ->label(fn (): string => $this->invoiceLabel($this->resolveOrder()))
            ->icon('heroicon-o-document-arrow-down')
            ->color(fn (): string => $this->invoiceColor($this->resolveOrder()))
            ->action(function (): void {
                $order = $this->resolveOrder();
                if (! $order) {
                    return;
                }

                $invoice = $this->invoiceFor($order);

                if ($invoice?->isFinalized() && $invoice->pennylane_id) {
                    $url = URL::signedRoute('pennylane.invoice.pdf', ['order' => $order->id]);
                    $this->redirectBrowserTo($url);

                    return;
                }

                if ($invoice?->status === PennylaneInvoice::STATUS_FAILED) {
                    SyncOrderInvoiceJob::dispatch($order->id);
                    Notification::make()
                        ->info()
                        ->title('Resynchronisation relancée')
                        ->body('Un job de resync Pennylane a été dispatché. Réessayez dans quelques secondes.')
                        ->send();

                    return;
                }

                Notification::make()
                    ->warning()
                    ->title('Facture bientôt disponible')
                    ->body('La facture Pennylane est en cours d\'émission. Recharge la page dans un instant.')
                    ->send();
            })
            ->modalHeading(fn (): ?string => $this->invoiceModalHeading())
            ->modalContent(fn (): ?HtmlString => $this->invoiceModalContent())
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth('md')
            ->modal(fn (): bool => $this->shouldShowErrorModal());
    }

    private function creditNotesAction(): ?ActionGroup
    {
        $order = $this->resolveOrder();
        if (! $order) {
            return null;
        }

        $refunds = $order->refunds()->where('success', true)->get();
        if ($refunds->isEmpty()) {
            return null;
        }

        $actions = [];
        foreach ($refunds as $refund) {
            $record = PennylaneInvoice::where('type', PennylaneInvoice::TYPE_CREDIT_NOTE)
                ->where('transaction_id', $refund->id)
                ->first();

            $actions[] = $this->creditNoteAction($refund, $record);
        }

        return ActionGroup::make($actions)
            ->label('Avoirs Pennylane ('.count($refunds).')')
            ->icon('heroicon-o-receipt-refund')
            ->button();
    }

    private function creditNoteAction(Transaction $refund, ?PennylaneInvoice $record): Action
    {
        $amount = $refund->amount->formatted ?? '';
        $ref = $refund->reference ?: (string) $refund->id;

        return Action::make('pennylane_credit_note_'.$refund->id)
            ->label(function () use ($record, $amount, $ref): string {
                if ($record?->isFinalized()) {
                    return 'Avoir '.$record->pennylane_invoice_number.' ('.$amount.')';
                }
                if ($record?->status === PennylaneInvoice::STATUS_FAILED) {
                    return '⚠ Avoir en échec ('.$amount.') — '.$ref;
                }

                return 'Avoir bientôt disponible ('.$amount.')';
            })
            ->icon(fn () => $record?->isFinalized()
                ? 'heroicon-o-document-arrow-down'
                : ($record?->status === PennylaneInvoice::STATUS_FAILED
                    ? 'heroicon-o-exclamation-triangle'
                    : 'heroicon-o-clock'))
            ->color(fn () => $record?->isFinalized()
                ? 'success'
                : ($record?->status === PennylaneInvoice::STATUS_FAILED ? 'danger' : 'gray'))
            ->action(function () use ($refund, $record): void {
                if ($record?->isFinalized() && $record->pennylane_id) {
                    $url = URL::signedRoute('pennylane.credit-note.pdf', ['transaction' => $refund->id]);
                    $this->redirectBrowserTo($url);

                    return;
                }

                if ($record?->status === PennylaneInvoice::STATUS_FAILED) {
                    SyncRefundCreditNoteJob::dispatch($refund->id);
                    Notification::make()
                        ->info()
                        ->title('Resync avoir dispatchée')
                        ->send();

                    return;
                }

                Notification::make()
                    ->warning()
                    ->title('Avoir bientôt disponible')
                    ->body('L\'avoir Pennylane est en cours d\'émission.')
                    ->send();
            })
            ->modalHeading(fn (): ?string => $record?->status === PennylaneInvoice::STATUS_FAILED
                ? 'Erreur de synchronisation — '.$ref
                : null)
            ->modalContent(fn (): ?HtmlString => $record?->status === PennylaneInvoice::STATUS_FAILED
                ? $this->errorModalContent($record, 'Avoir')
                : null)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modal(fn (): bool => $record?->status === PennylaneInvoice::STATUS_FAILED);
    }

    private function invoiceLabel(?Order $order): string
    {
        $invoice = $this->invoiceFor($order);

        if ($invoice?->isFinalized()) {
            return 'Télécharger facture '.$invoice->pennylane_invoice_number;
        }
        if ($invoice?->status === PennylaneInvoice::STATUS_FAILED) {
            return '⚠ Facture en échec — voir détails';
        }

        return 'Facture bientôt disponible';
    }

    private function invoiceColor(?Order $order): string
    {
        $invoice = $this->invoiceFor($order);

        return match ($invoice?->status) {
            PennylaneInvoice::STATUS_FINALIZED => 'primary',
            PennylaneInvoice::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    private function shouldShowErrorModal(): bool
    {
        $invoice = $this->invoiceFor($this->resolveOrder());

        return $invoice?->status === PennylaneInvoice::STATUS_FAILED;
    }

    private function invoiceModalHeading(): ?string
    {
        return $this->shouldShowErrorModal()
            ? 'Erreur de synchronisation Pennylane'
            : null;
    }

    private function invoiceModalContent(): ?HtmlString
    {
        $invoice = $this->invoiceFor($this->resolveOrder());
        if (! $invoice || $invoice->status !== PennylaneInvoice::STATUS_FAILED) {
            return null;
        }

        return $this->errorModalContent($invoice, 'Facture');
    }

    private function errorModalContent(PennylaneInvoice $invoice, string $kind): HtmlString
    {
        $error = e((string) ($invoice->last_error ?? 'Erreur inconnue'));
        $ref = e((string) $invoice->external_reference);
        $updatedAt = e((string) ($invoice->updated_at?->format('Y-m-d H:i:s') ?? '—'));

        return new HtmlString(<<<HTML
            <div class="space-y-3 text-sm">
                <div class="rounded-md bg-red-50 p-3 text-red-900 dark:bg-red-950/40 dark:text-red-200">
                    <div class="font-medium">{$kind} non émise dans Pennylane</div>
                    <div class="mt-1 text-xs opacity-80">Référence : {$ref}</div>
                    <div class="text-xs opacity-80">Dernière tentative : {$updatedAt}</div>
                </div>
                <div>
                    <div class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">Mini-log</div>
                    <pre class="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-gray-900 p-3 text-xs text-gray-100">{$error}</pre>
                </div>
                <div class="text-xs text-gray-500">
                    Clique sur le bouton pour relancer une synchronisation.
                </div>
            </div>
        HTML);
    }

    private function invoiceFor(?Order $order): ?PennylaneInvoice
    {
        if (! $order) {
            return null;
        }

        return PennylaneInvoice::where('type', PennylaneInvoice::TYPE_INVOICE)
            ->where('order_id', $order->id)
            ->first();
    }

    private function resolveOrder(): ?Order
    {
        $caller = $this->caller ?? null;
        if (! $caller) {
            return null;
        }

        return $caller->record ?? null;
    }

    private function redirectBrowserTo(string $url): void
    {
        $livewire = $this->caller;
        if ($livewire && method_exists($livewire, 'redirect')) {
            $livewire->redirect($url);
        }
    }
}

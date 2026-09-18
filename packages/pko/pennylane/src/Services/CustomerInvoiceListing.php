<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Pko\Account\Contracts\CustomerInvoices;
use Pko\Pennylane\Models\PennylaneInvoice;

final class CustomerInvoiceListing implements CustomerInvoices
{
    public function forCustomer(int $customerId): LengthAwarePaginator
    {
        return PennylaneInvoice::query()
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNotNull('pennylane_id')
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customerId))
            ->with(['order.currency', 'transaction'])
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function (PennylaneInvoice $invoice): array {
                $credit = $invoice->type === PennylaneInvoice::TYPE_CREDIT_NOTE;
                $cents = $credit ? $invoice->transaction?->amount?->value : $invoice->order->total->value;
                $cents = $cents === null ? null : ($credit ? -abs((int) $cents) : (int) $cents);
                $total = $cents === null ? '—' : ($cents < 0 ? '−' : '')
                    .number_format(intdiv(abs($cents), 100), 0, ',', ' ')
                    .','.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT)
                    .' '.$invoice->order->currency_code;

                return [
                    'date' => $invoice->payload_snapshot['date'] ?? $invoice->created_at->toDateString(),
                    'number' => $invoice->pennylane_invoice_number ?: '—',
                    'order_reference' => $invoice->order->reference ?: $invoice->order_id,
                    'order_url' => route('account.order.view', $invoice->order_id),
                    'type' => $credit ? 'Avoir' : 'Facture',
                    'total' => $total,
                    'download_url' => route('pennylane.customer.pdf', $invoice->id),
                ];
            });
    }
}

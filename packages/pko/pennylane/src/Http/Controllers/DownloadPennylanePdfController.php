<?php

declare(strict_types=1);

namespace Pko\Pennylane\Http\Controllers;

use Illuminate\Http\Request;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Account\Support\AccountContext;
use Pko\Pennylane\Api\Exceptions\PennylaneException;
use Pko\Pennylane\Models\PennylaneInvoice;
use Pko\Pennylane\Services\InvoicePdfFetcher;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadPennylanePdfController
{
    public function __construct(
        private readonly InvoicePdfFetcher $pdf,
    ) {}

    public function invoice(Request $request, Order $order): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $orderModel = $order;

        $record = PennylaneInvoice::where('type', PennylaneInvoice::TYPE_INVOICE)
            ->where('order_id', $orderModel->id)
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNotNull('pennylane_id')
            ->firstOrFail();

        $filename = $record->pdfFilename();

        return $this->streamPdf((int) $record->pennylane_id, $filename);
    }

    public function creditNote(Request $request, Transaction $transaction): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $txn = $transaction;

        $record = PennylaneInvoice::where('type', PennylaneInvoice::TYPE_CREDIT_NOTE)
            ->where('transaction_id', $txn->id)
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNotNull('pennylane_id')
            ->firstOrFail();

        $filename = $record->pdfFilename();

        return $this->streamPdf((int) $record->pennylane_id, $filename);
    }

    public function customer(int $invoice): StreamedResponse
    {
        $customer = AccountContext::customer();
        abort_unless($customer, 404);
        $record = PennylaneInvoice::query()
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNotNull('pennylane_id')
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->findOrFail($invoice);

        return $this->streamPdf((int) $record->pennylane_id, $record->pdfFilename());
    }

    private function streamPdf(int $pennylaneId, string $filename): StreamedResponse
    {
        try {
            $body = $this->pdf->fetch($pennylaneId);
        } catch (PennylaneException $exception) {
            abort(503, $exception->getMessage(), ['Retry-After' => '60', 'Cache-Control' => 'private, no-store']);
        }

        return response()->streamDownload(
            fn () => print $body,
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Length' => (string) strlen($body),
            ],
        );
    }
}

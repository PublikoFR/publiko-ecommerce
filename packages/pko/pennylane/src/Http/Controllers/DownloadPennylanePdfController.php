<?php

declare(strict_types=1);

namespace Pko\Pennylane\Http\Controllers;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;
use Pko\Pennylane\Models\PennylaneInvoice;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadPennylanePdfController
{
    public function __construct(
        private readonly CustomerInvoicesResource $invoices,
        private readonly HttpFactory $http,
    ) {}

    public function invoice(Request $request, int $order): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $orderModel = Order::findOrFail($order);

        $record = PennylaneInvoice::where('type', PennylaneInvoice::TYPE_INVOICE)
            ->where('order_id', $orderModel->id)
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNotNull('pennylane_id')
            ->firstOrFail();

        $filename = 'Facture-'.($record->pennylane_invoice_number ?: 'order-'.$orderModel->id).'.pdf';

        return $this->streamPdf((int) $record->pennylane_id, $filename);
    }

    public function creditNote(Request $request, int $transaction): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $txn = Transaction::findOrFail($transaction);

        $record = PennylaneInvoice::where('type', PennylaneInvoice::TYPE_CREDIT_NOTE)
            ->where('transaction_id', $txn->id)
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNotNull('pennylane_id')
            ->firstOrFail();

        $filename = 'Avoir-'.($record->pennylane_invoice_number ?: 'refund-'.$txn->id).'.pdf';

        return $this->streamPdf((int) $record->pennylane_id, $filename);
    }

    private function streamPdf(int $pennylaneId, string $filename): StreamedResponse
    {
        $url = $this->invoices->pdfUrl($pennylaneId);
        abort_if($url === null, 404, 'PDF introuvable côté Pennylane.');

        $body = $this->http->timeout(30)->get($url)->throw()->body();

        return response()->streamDownload(
            fn () => print $body,
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($body),
            ],
        );
    }
}

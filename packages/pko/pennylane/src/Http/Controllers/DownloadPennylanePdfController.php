<?php

declare(strict_types=1);

namespace Pko\Pennylane\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Pko\Account\Support\AccountContext;
use Pko\Pennylane\Api\Exceptions\PennylaneException;
use Pko\Pennylane\Models\PennylaneInvoice;
use Pko\Pennylane\Services\InvoicePdfFetcher;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final class DownloadPennylanePdfController
{
    public function __construct(
        private readonly InvoicePdfFetcher $pdf,
    ) {}

    public function invoice(Request $request, Order $order): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $record = PennylaneInvoice::where('type', PennylaneInvoice::TYPE_INVOICE)
            ->where('order_id', $order->id)
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNotNull('pennylane_id')
            ->firstOrFail();

        return $this->streamPdf((int) $record->pennylane_id, $record->pdfFilename(), $request->boolean('inline'));
    }

    public function creditNote(Request $request, Transaction $transaction): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $record = PennylaneInvoice::where('type', PennylaneInvoice::TYPE_CREDIT_NOTE)
            ->where('transaction_id', $transaction->id)
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNotNull('pennylane_id')
            ->firstOrFail();

        return $this->streamPdf((int) $record->pennylane_id, $record->pdfFilename(), $request->boolean('inline'));
    }

    public function customer(Request $request, int $invoice): Response
    {
        $customer = AccountContext::customer();
        abort_unless($customer, 404);
        $record = PennylaneInvoice::query()
            ->where('status', PennylaneInvoice::STATUS_FINALIZED)
            ->whereNotNull('pennylane_id')
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->findOrFail($invoice);

        return $this->streamPdf((int) $record->pennylane_id, $record->pdfFilename(), $request->boolean('inline'));
    }

    /**
     * `inline` affiche le PDF dans le navigateur au lieu de le télécharger.
     * Il ne change rien aux contrôles d'accès : garde staff + signature côté
     * admin (le paramètre fait alors partie de l'URL signée), compte connecté
     * propriétaire de la facture côté client.
     */
    private function streamPdf(int $pennylaneId, string $filename, bool $inline = false): Response
    {
        try {
            $body = $this->pdf->fetch($pennylaneId);
        } catch (PennylaneException $exception) {
            abort(503, $exception->getMessage(), ['Retry-After' => '60', 'Cache-Control' => 'private, no-store']);
        }

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                Str::ascii($filename),
            ),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Length' => (string) strlen($body),
        ]);
    }
}

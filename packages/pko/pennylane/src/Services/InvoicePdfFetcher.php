<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Pko\Pennylane\Api\Exceptions\InvoicePdfNotReady;
use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Api\Exceptions\PennylaneException;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;

final class InvoicePdfFetcher
{
    public function __construct(
        private readonly CustomerInvoicesResource $invoices,
        private readonly HttpFactory $http,
    ) {}

    public function fetch(int $pennylaneId): string
    {
        try {
            $url = $this->invoices->pdfUrl($pennylaneId);
            if (! $url) {
                throw new InvoicePdfNotReady;
            }

            // L'URL reste exclusivement en mémoire côté serveur, sans Bearer API.
            $response = $this->http->timeout(30)->get($url);
            if ($response->status() === 409) {
                throw new InvoicePdfNotReady;
            }
            $body = $response->throw()->body();
            if (! str_starts_with($body, '%PDF-')) {
                throw new PennylaneException('Document PDF invalide.');
            }

            return $body;
        } catch (InvoicePdfNotReady $exception) {
            throw $exception;
        } catch (PennylaneApiException $exception) {
            if ($exception->status === 409) {
                throw new InvoicePdfNotReady;
            }
            throw new PennylaneException('Le PDF est temporairement indisponible.');
        } catch (\Throwable) {
            // Ne pas exposer l'URL publique via les messages d'erreur HTTP.
            throw new PennylaneException('Le PDF est temporairement indisponible.');
        }
    }
}

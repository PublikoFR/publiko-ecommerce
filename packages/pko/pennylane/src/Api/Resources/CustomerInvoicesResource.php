<?php

declare(strict_types=1);

namespace Pko\Pennylane\Api\Resources;

use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Api\PennylaneClient;

final class CustomerInvoicesResource
{
    private const ENDPOINT = '/customer_invoices';

    public function __construct(private readonly PennylaneClient $client) {}

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function create(array $payload): array
    {
        $response = $this->client->post(self::ENDPOINT, $payload);

        return (array) $response->json();
    }

    /**
     * @return array<string,mixed>
     */
    public function finalize(int $invoiceId): array
    {
        $response = $this->client->put(self::ENDPOINT."/{$invoiceId}/finalize");

        return (array) $response->json();
    }

    /**
     * @return array<string,mixed>
     */
    public function get(int $invoiceId): array
    {
        $response = $this->client->get(self::ENDPOINT."/{$invoiceId}");

        return (array) $response->json();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByExternalReference(string $externalReference): ?array
    {
        try {
            $response = $this->client->get(self::ENDPOINT, [
                'filter' => [
                    ['field' => 'external_reference', 'operator' => 'eq', 'value' => $externalReference],
                ],
                'limit' => 1,
            ]);
        } catch (PennylaneApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }
            throw $e;
        }

        $items = (array) ($response->json('items') ?? []);

        return $items[0] ?? null;
    }

    /**
     * Return a direct (signed) URL to the invoice PDF hosted by Pennylane, or null.
     */
    public function pdfUrl(int $invoiceId): ?string
    {
        $data = $this->get($invoiceId);

        foreach (['public_file_url', 'file_url', 'pdf_url', 'download_url'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public function changelog(?string $startDate = null, ?string $cursor = null, int $limit = 200): array
    {
        $query = ['limit' => $limit];
        if ($startDate) {
            $query['start_date'] = $startDate;
        }
        if ($cursor) {
            $query['cursor'] = $cursor;
        }

        $response = $this->client->get('/changelogs/customer_invoices', $query);

        return (array) $response->json();
    }
}

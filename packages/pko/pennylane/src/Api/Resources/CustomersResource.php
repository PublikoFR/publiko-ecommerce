<?php

declare(strict_types=1);

namespace Pko\Pennylane\Api\Resources;

use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Api\PennylaneClient;

final class CustomersResource
{
    /** Lecture et recherche, tous types confondus. */
    private const ENDPOINT = '/customers';

    public function __construct(private readonly PennylaneClient $client) {}

    /**
     * La v2 sépare la création par type : `/company_customers` ou `/individual_customers`.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function create(array $payload, bool $isCompany): array
    {
        $response = $this->client->post(self::writeEndpoint($isCompany), $payload);

        return (array) $response->json();
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function update(int $id, array $payload, bool $isCompany): array
    {
        $response = $this->client->put(self::writeEndpoint($isCompany)."/{$id}", $payload);

        return (array) $response->json();
    }

    private static function writeEndpoint(bool $isCompany): string
    {
        return $isCompany ? '/company_customers' : '/individual_customers';
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
}

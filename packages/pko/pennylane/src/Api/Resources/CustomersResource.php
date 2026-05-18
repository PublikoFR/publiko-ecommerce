<?php

declare(strict_types=1);

namespace Pko\Pennylane\Api\Resources;

use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Api\PennylaneClient;

final class CustomersResource
{
    private const ENDPOINT = '/customers';

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
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function update(int $id, array $payload): array
    {
        $response = $this->client->put(self::ENDPOINT."/{$id}", $payload);

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
}

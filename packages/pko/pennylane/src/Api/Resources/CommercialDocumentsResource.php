<?php

declare(strict_types=1);

namespace Pko\Pennylane\Api\Resources;

use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Api\PennylaneClient;

/**
 * Documents commerciaux Pennylane : proforma, bon de livraison
 * (`shipping_order`), bon de commande (`purchasing_order`).
 *
 * Pas de brouillon ni de finalisation : le document est numéroté par
 * Pennylane dès sa création. Scope `commercial_documents:all`.
 */
final class CommercialDocumentsResource
{
    private const ENDPOINT = '/commercial_documents';

    public const TYPE_SHIPPING_ORDER = 'shipping_order';

    public function __construct(private readonly PennylaneClient $client) {}

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function create(array $payload): array
    {
        return (array) $this->client->post(self::ENDPOINT, $payload)->json();
    }

    /**
     * @return array<string,mixed>
     */
    public function get(int $documentId): array
    {
        return (array) $this->client->get(self::ENDPOINT."/{$documentId}")->json();
    }

    /**
     * L'API ne filtre pas sur `external_reference` (seulement `id`,
     * `customer_id`, `document_type`) : on liste les documents du client, du
     * plus récent au plus ancien, et on compare la référence ici. Un filtre
     * ignoré ou un document d'une autre commande ne doit jamais être repris.
     *
     * @return array<string,mixed>|null
     */
    public function findByExternalReference(string $externalReference, int $customerId, string $documentType, int $maxPages = 5): ?array
    {
        $cursor = null;

        for ($page = 0; $page < $maxPages; $page++) {
            try {
                $response = $this->client->get(self::ENDPOINT, array_filter([
                    'filter' => [
                        ['field' => 'customer_id', 'operator' => 'eq', 'value' => (string) $customerId],
                        ['field' => 'document_type', 'operator' => 'eq', 'value' => $documentType],
                    ],
                    'sort' => '-id',
                    'limit' => 100,
                    'cursor' => $cursor,
                ], fn ($v) => $v !== null));
            } catch (PennylaneApiException $e) {
                if ($e->isNotFound()) {
                    return null;
                }
                throw $e;
            }

            foreach ((array) ($response->json('items') ?? []) as $item) {
                if (is_array($item) && ($item['external_reference'] ?? null) === $externalReference) {
                    return $item;
                }
            }

            $cursor = $response->json('next_cursor');
            if (! $response->json('has_more') || ! is_string($cursor) || $cursor === '') {
                return null;
            }
        }

        return null;
    }

    /**
     * URL publique du PDF, `null` tant que Pennylane ne l'a pas généré.
     */
    public function pdfUrl(int $documentId): ?string
    {
        $url = $this->get($documentId)['public_file_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}

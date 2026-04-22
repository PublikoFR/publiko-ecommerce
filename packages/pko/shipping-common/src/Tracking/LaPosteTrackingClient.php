<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Tracking;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

/**
 * Unified tracking client for ALL La Poste products (Colissimo, Chronopost,
 * Lettre suivie, etc.). Uses the public REST API `api.laposte.fr/suivi/v2`
 * which requires an "Okapi" API key (obtainable from developer.laposte.fr).
 *
 * Normalises La Poste's status codes into a stable vocabulary (in_transit,
 * out_for_delivery, delivered, returned, failed, unknown) so downstream code
 * never has to learn La Poste internals.
 */
class LaPosteTrackingClient
{
    public const BASE_URL = 'https://api.laposte.fr/suivi/v2';

    public const PUBLIC_TRACKING_URL = 'https://www.laposte.fr/outils/suivre-vos-envois?code=';

    public function __construct(
        protected HttpFactory $http,
        protected ?string $apiKey = null,
        protected int $timeoutSeconds = 5,
    ) {}

    public function track(string $trackingNumber, string $lang = 'fr_FR'): TrackingStatus
    {
        $apiKey = $this->apiKey ?? secret('laposte.api_key');
        if (empty($apiKey)) {
            throw TrackingException::missingApiKey();
        }

        $response = $this->request($apiKey)->get("/idships/{$trackingNumber}", ['lang' => $lang]);

        if ($response->status() === 404) {
            throw TrackingException::notFound($trackingNumber);
        }

        if (! $response->successful()) {
            throw TrackingException::unexpectedStatus($response->status(), (string) $response->body());
        }

        $payload = $response->json('shipment', []);
        if (! is_array($payload) || $payload === []) {
            throw TrackingException::unexpectedStatus($response->status(), (string) $response->body());
        }

        return $this->parse($payload, $trackingNumber);
    }

    protected function request(string $apiKey): PendingRequest
    {
        return $this->http
            ->baseUrl(self::BASE_URL)
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->withHeaders(['X-Okapi-Key' => $apiKey]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function parse(array $payload, string $trackingNumber): TrackingStatus
    {
        $events = [];
        foreach (($payload['event'] ?? $payload['timeline'] ?? []) as $event) {
            $events[] = [
                'at' => (string) ($event['date'] ?? ''),
                'code' => (string) ($event['code'] ?? ''),
                'label' => (string) ($event['label'] ?? $event['message'] ?? ''),
            ];
        }

        $laPosteStatus = (string) ($payload['idShip']
            ?? $payload['status']
            ?? (($events[0]['code'] ?? null) ?? 'UNKNOWN'));

        $normalized = $this->normalizeStatus($laPosteStatus, $events);
        $statusUpdatedAt = $this->parseDate($events[0]['at'] ?? null);
        $deliveredAt = $normalized === 'delivered'
            ? $this->parseDate($events[0]['at'] ?? null)
            : null;

        return new TrackingStatus(
            status: $normalized,
            statusUpdatedAt: $statusUpdatedAt,
            deliveredAt: $deliveredAt,
            events: $events,
            publicUrl: self::PUBLIC_TRACKING_URL.$trackingNumber,
        );
    }

    /**
     * Map La Poste event codes to a stable vocabulary.
     * Codes reference: https://developer.laposte.fr/products/suivi/latest
     *
     * @param  array<int, array{code: string}>  $events
     */
    protected function normalizeStatus(string $apiStatus, array $events): string
    {
        $code = strtoupper((string) ($events[0]['code'] ?? $apiStatus));

        return match (true) {
            in_array($code, ['DI1', 'DI2'], true) => 'delivered',
            str_starts_with($code, 'ET_') => 'in_transit',
            in_array($code, ['MLE', 'PCH'], true) => 'out_for_delivery',
            in_array($code, ['DE1', 'DE2'], true) => 'failed',
            str_starts_with($code, 'AG_') => 'returned',
            default => 'unknown',
        };
    }

    protected function parseDate(?string $value): ?DateTimeImmutable
    {
        if (empty($value)) {
            return null;
        }
        try {
            return new DateTimeImmutable($value, new DateTimeZone('Europe/Paris'));
        } catch (Throwable) {
            return null;
        }
    }
}

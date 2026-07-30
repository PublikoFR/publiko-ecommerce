<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Services;

use Pko\ShippingChronopost\Exceptions\PickupPointException;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * Pure SOAP client for Chronopost PointRelaisServiceWS.
 *
 * Official WSDL: https://ws.chronopost.fr/recherchebt-wsdl/PointRelaisServiceWS?wsdl
 * Method: recherchePointChronopostInter
 */
class PickupPointSoapClient
{
    public const DEFAULT_WSDL = 'https://ws.chronopost.fr/recherchebt-wsdl/PointRelaisServiceWS?wsdl';

    /**
     * @param  array{account?: string, password?: string}  $credentials
     */
    public function __construct(
        protected array $credentials,
        protected ?string $wsdl = null,
        protected ?SoapClient $client = null,
        protected int $timeoutSeconds = 8,
    ) {}

    /**
     * @return array<int, array{
     *     id: string, name: string, address1: string, postcode: string, city: string,
     *     country_code: string, distance_km: float|null,
     *     latitude: float|null, longitude: float|null,
     *     opening_hours: string|null
     * }>
     *
     * @throws PickupPointException
     */
    public function search(
        string $postcode,
        string $countryCode = 'FR',
        ?string $serviceCode = null,
    ): array {
        $account = (string) ($this->credentials['account'] ?? '');
        $password = (string) ($this->credentials['password'] ?? '');

        if ($account === '' || $password === '') {
            throw new PickupPointException('Chronopost pickup: missing account credentials');
        }

        try {
            $client = $this->client ?? $this->buildSoapClient();

            $response = $client->recherchePointChronopostInter([
                'accountNumber'  => $account,
                'password'       => $password,
                'zipCode'        => $postcode,
                'city'           => '',
                'countryCode'    => $countryCode,
                'type'           => '',
                'productCode'    => $serviceCode ?? '',
                'service'        => '',
                'weight'         => '',
                'shippingDate'   => '',
                'maxPointChronopost' => 20,
            ]);
        } catch (SoapFault $e) {
            throw new PickupPointException(
                "Chronopost pickup SOAP fault: {$e->getMessage()}",
                previous: $e
            );
        } catch (Throwable $e) {
            throw new PickupPointException(
                "Chronopost pickup error: {$e->getMessage()}",
                previous: $e
            );
        }

        return $this->parseResponse($response);
    }

    protected function buildSoapClient(): SoapClient
    {
        return new SoapClient($this->wsdl ?? self::DEFAULT_WSDL, [
            'trace'              => false,
            'exceptions'         => true,
            'connection_timeout' => $this->timeoutSeconds,
            'cache_wsdl'         => WSDL_CACHE_BOTH,
            // connection_timeout borne uniquement le TCP handshake ; stream_context
            // borne la phase de lecture (WS lent → repli sur [] après $timeoutSeconds).
            'stream_context'     => stream_context_create(['http' => ['timeout' => $this->timeoutSeconds]]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseResponse(mixed $response): array
    {
        $payload = $response->return ?? $response;

        $errorCode = (string) ($payload->errorCode ?? '0');
        if ($errorCode !== '0' && $errorCode !== '') {
            $message = (string) ($payload->errorMessage ?? 'unknown');
            throw new PickupPointException("Chronopost pickup API error [{$errorCode}]: {$message}");
        }

        $rawPoints = $payload->listePointRelais ?? [];

        if (is_object($rawPoints)) {
            $rawPoints = [$rawPoints];
        }

        if (! is_array($rawPoints)) {
            return [];
        }

        $points = [];
        foreach ($rawPoints as $point) {
            $points[] = [
                'id'            => (string) ($point->identifiant ?? ''),
                'name'          => (string) ($point->nom ?? ''),
                'address1'      => (string) ($point->adresse1 ?? ''),
                'postcode'      => (string) ($point->codePostal ?? ''),
                'city'          => (string) ($point->localite ?? ''),
                'country_code'  => (string) ($point->codePays ?? 'FR'),
                'distance_km'   => isset($point->distanceEnMetre)
                    ? round((float) $point->distanceEnMetre / 1000, 2)
                    : null,
                'latitude'      => isset($point->coordGeolocalisationLatitude)
                    ? (float) str_replace(',', '.', (string) $point->coordGeolocalisationLatitude)
                    : null,
                'longitude'     => isset($point->coordGeolocalisationLongitude)
                    ? (float) str_replace(',', '.', (string) $point->coordGeolocalisationLongitude)
                    : null,
                'opening_hours' => $this->parseOpeningHours($point),
            ];
        }

        return $points;
    }

    private function parseOpeningHours(object $point): ?string
    {
        $horaires = $point->listeHoraireOuverture ?? null;
        if (! $horaires) {
            return null;
        }

        if (is_object($horaires)) {
            $horaires = [$horaires];
        }

        if (! is_array($horaires)) {
            return null;
        }

        $lines = [];
        foreach ($horaires as $h) {
            $day    = (string) ($h->jour ?? '');
            $open   = (string) ($h->listeHoraire->ouvertureMatin ?? '');
            $close  = (string) ($h->listeHoraire->fermetureApresMidi ?? '');

            if ($day !== '' && $open !== '' && $close !== '') {
                $lines[] = "{$day}: {$open}-{$close}";
            }
        }

        return $lines ? implode(', ', $lines) : null;
    }
}

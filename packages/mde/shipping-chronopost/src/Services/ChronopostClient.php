<?php

declare(strict_types=1);

namespace Mde\ShippingChronopost\Services;

use ladromelaboratoire\chronopostws\chronopost;
use Mde\ShippingCommon\Contracts\CarrierClient;
use Mde\ShippingCommon\Dto\QuoteRequest;
use Mde\ShippingCommon\Dto\QuoteResponse;
use Mde\ShippingCommon\Dto\ShipmentRequest;
use Mde\ShippingCommon\Dto\ShipmentResponse;
use RuntimeException;
use Throwable;

class ChronopostClient implements CarrierClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function carrierCode(): string
    {
        return 'chronopost';
    }

    /**
     * @return list<QuoteResponse>
     */
    public function quote(QuoteRequest $request): array
    {
        $grid = $this->config['grid'] ?? [];
        $maxWeight = (float) ($this->config['max_weight_kg'] ?? 30);

        if ($request->weightKg > $maxWeight) {
            return [];
        }

        $priceCents = null;
        foreach ($grid as $bracket) {
            if ($request->weightKg <= (float) $bracket['max_kg']) {
                $priceCents = (int) $bracket['price'];
                break;
            }
        }

        if ($priceCents === null) {
            return [];
        }

        $services = $this->config['services'] ?? [];
        $quotes = [];

        foreach ($services as $code => $service) {
            if (! ($service['enabled'] ?? false)) {
                continue;
            }

            if ($request->serviceCodes !== [] && ! in_array((string) $code, $request->serviceCodes, true)) {
                continue;
            }

            $quotes[] = new QuoteResponse(
                serviceCode: (string) $code,
                serviceLabel: (string) $service['label'],
                priceCents: $priceCents,
            );
        }

        return $quotes;
    }

    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        $credentials = $this->config['credentials'] ?? [];
        $account = (string) ($credentials['account'] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        $subAccount = (string) ($credentials['sub_account'] ?? '');

        if ($account === '' || $password === '') {
            throw new RuntimeException('Chronopost credentials missing (CHRONOPOST_ACCOUNT / CHRONOPOST_PASSWORD).');
        }

        $shipper = $request->shipper;
        $recipient = $request->recipient;

        $labelsData = [
            'headerValue' => [
                'accountNumber' => $account,
                'idEmit' => 'CHRFR',
                'subAccount' => $subAccount,
            ],
            'shipperValue' => [
                'shipperAdress1' => (string) ($shipper['street'] ?? ''),
                'shipperCity' => (string) ($shipper['city'] ?? ''),
                'shipperContactName' => (string) ($shipper['name'] ?? ''),
                'shipperCountry' => (string) ($shipper['country'] ?? 'FR'),
                'shipperEmail' => (string) ($shipper['email'] ?? ''),
                'shipperName' => (string) ($shipper['name'] ?? ''),
                'shipperPhone' => (string) ($shipper['phone'] ?? ''),
                'shipperZipCode' => (string) ($shipper['zip'] ?? ''),
                'shipperType' => '2',
            ],
            'customerValue' => [
                'customerAdress1' => (string) ($shipper['street'] ?? ''),
                'customerCity' => (string) ($shipper['city'] ?? ''),
                'customerContactName' => (string) ($shipper['name'] ?? ''),
                'customerCountry' => (string) ($shipper['country'] ?? 'FR'),
                'customerEmail' => (string) ($shipper['email'] ?? ''),
                'customerName' => (string) ($shipper['name'] ?? ''),
                'customerPhone' => (string) ($shipper['phone'] ?? ''),
                'customerZipCode' => (string) ($shipper['zip'] ?? ''),
            ],
            'recipientValue' => [
                'recipientAdress1' => (string) ($recipient['street'] ?? ''),
                'recipientCity' => (string) ($recipient['city'] ?? ''),
                'recipientContactName' => (string) ($recipient['name'] ?? ''),
                'recipientCountry' => (string) ($recipient['country'] ?? 'FR'),
                'recipientEmail' => (string) ($recipient['email'] ?? ''),
                'recipientName' => (string) (($recipient['company'] ?? null) ?: ($recipient['name'] ?? '')),
                'recipientName2' => (string) ($recipient['name'] ?? ''),
                'recipientPhone' => (string) ($recipient['phone'] ?? ''),
                'recipientZipCode' => (string) ($recipient['zip'] ?? ''),
                'recipientType' => '1',
            ],
            'refValue' => [
                'customerSkybillNumber' => $request->orderReference,
                'shipperRef' => $request->orderReference,
            ],
            'skybillValue' => [
                'bulkNumber' => 1,
                'evtCode' => 'DC',
                'productCode' => $request->serviceCode,
                'service' => '0',
                'shipDate' => date('Y-m-d\TH:i:s'),
                'shipHour' => date('H'),
                'weight' => max(0.1, $request->weightKg),
                'weightUnit' => 'KGM',
                'objectType' => 'MAR',
                'portCurrency' => 'EUR',
                'codValue' => '0',
                'insuredCurrency' => 'EUR',
                'insuredValue' => '0',
                'customsCurrency' => 'EUR',
                'customsValue' => '0',
                'codCurrency' => 'EUR',
                'skybillRank' => '1',
                'as' => '0',
                'content1' => 'Colis',
            ],
            'skybillParamsValue' => [
                'mode' => $this->config['label_format'] ?? 'PDF',
            ],
            'password' => $password,
            'modeRetour' => 2,
            'numberOfParcel' => 1,
            'version' => '2.0',
            'multiParcel' => 'N',
        ];

        $client = new chronopost(false, false, false, false);

        try {
            $success = $client->makeShippingLabels($labelsData, true, false);
        } catch (Throwable $e) {
            throw new RuntimeException('Chronopost SOAP call failed: '.$e->getMessage(), 0, $e);
        }

        if ($success !== true) {
            throw new RuntimeException('Chronopost shipping label creation failed (RFL or SOAP error).');
        }

        $skybill = $client->shipment->skybillValue[0] ?? null;
        $trackingNumber = (string) ($skybill->skybillNumber ?? '');

        if ($trackingNumber === '') {
            throw new RuntimeException('Chronopost did not return a tracking number.');
        }

        $labels = $client->shipment->labels ?? [];
        $labelBase64 = '';
        foreach ($labels as $format => $label) {
            if ($label !== null && $label !== '') {
                $labelBase64 = is_string($label) ? base64_encode($label) : '';
                break;
            }
        }

        return new ShipmentResponse(
            trackingNumber: $trackingNumber,
            labelPdfBase64: $labelBase64,
            rawResponse: [
                'tracking_number' => $trackingNumber,
                'reservation_number' => $client->shipment->reservationNumber ?? null,
            ],
        );
    }

    public function testCredentials(): bool
    {
        $credentials = $this->config['credentials'] ?? [];

        return (string) ($credentials['account'] ?? '') !== ''
            && (string) ($credentials['password'] ?? '') !== '';
    }
}

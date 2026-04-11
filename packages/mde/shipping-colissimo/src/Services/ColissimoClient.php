<?php

declare(strict_types=1);

namespace Mde\ShippingColissimo\Services;

use ColissimoPostage\ClassMap;
use ColissimoPostage\ServiceType\Generate;
use ColissimoPostage\StructType\Address;
use ColissimoPostage\StructType\Addressee;
use ColissimoPostage\StructType\GenerateLabel;
use ColissimoPostage\StructType\GenerateLabelRequest;
use ColissimoPostage\StructType\Letter;
use ColissimoPostage\StructType\OutputFormat;
use ColissimoPostage\StructType\Parcel;
use ColissimoPostage\StructType\Sender;
use ColissimoPostage\StructType\Service as ColissimoService;
use Mde\ShippingCommon\Contracts\CarrierClient;
use Mde\ShippingCommon\Dto\QuoteRequest;
use Mde\ShippingCommon\Dto\QuoteResponse;
use Mde\ShippingCommon\Dto\ShipmentRequest;
use Mde\ShippingCommon\Dto\ShipmentResponse;
use RuntimeException;
use Throwable;
use WsdlToPhp\PackageBase\AbstractSoapClientBase;

class ColissimoClient implements CarrierClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function carrierCode(): string
    {
        return 'colissimo';
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

            $surcharge = ((string) $code) === 'DOS' ? 100 : 0;

            $quotes[] = new QuoteResponse(
                serviceCode: (string) $code,
                serviceLabel: (string) $service['label'],
                priceCents: $priceCents + $surcharge,
            );
        }

        return $quotes;
    }

    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        $credentials = $this->config['credentials'] ?? [];
        $contractNumber = (string) ($credentials['contract_number'] ?? '');
        $password = (string) ($credentials['password'] ?? '');

        if ($contractNumber === '' || $password === '') {
            throw new RuntimeException('Colissimo credentials missing (COLISSIMO_CONTRACT / COLISSIMO_PASSWORD).');
        }

        $shipper = $request->shipper;
        $recipient = $request->recipient;

        $options = [
            AbstractSoapClientBase::WSDL_URL => (string) ($this->config['wsdl_url'] ?? 'https://ws.colissimo.fr/sls-ws/SlsServiceWS?wsdl'),
            AbstractSoapClientBase::WSDL_CLASSMAP => ClassMap::get(),
            AbstractSoapClientBase::WSDL_TRACE => true,
        ];

        try {
            $generate = new Generate($options);

            $service = (new ColissimoService())
                ->setProductCode($request->serviceCode)
                ->setDepositDate(date('Y-m-d'))
                ->setOrderNumber($request->orderReference)
                ->setCommercialName((string) ($shipper['name'] ?? 'MDE Distribution'));

            $parcel = (new Parcel())
                ->setWeight(max(0.01, $request->weightKg));

            $senderAddress = (new Address())
                ->setCompanyName((string) ($shipper['name'] ?? ''))
                ->setLastName((string) ($shipper['name'] ?? ''))
                ->setLine2((string) ($shipper['street'] ?? ''))
                ->setCountryCode((string) ($shipper['country'] ?? 'FR'))
                ->setCity((string) ($shipper['city'] ?? ''))
                ->setZipCode((string) ($shipper['zip'] ?? ''))
                ->setEmail((string) ($shipper['email'] ?? ''))
                ->setPhoneNumber((string) ($shipper['phone'] ?? ''));

            $sender = (new Sender())
                ->setSenderParcelRef($request->orderReference)
                ->setAddress($senderAddress);

            $recipientAddress = (new Address())
                ->setCompanyName((string) ($recipient['company'] ?? ''))
                ->setLastName((string) ($recipient['name'] ?? ''))
                ->setFirstName('')
                ->setLine2((string) ($recipient['street'] ?? ''))
                ->setCountryCode((string) ($recipient['country'] ?? 'FR'))
                ->setCity((string) ($recipient['city'] ?? ''))
                ->setZipCode((string) ($recipient['zip'] ?? ''))
                ->setEmail((string) ($recipient['email'] ?? ''))
                ->setPhoneNumber((string) ($recipient['phone'] ?? ''));

            $addressee = (new Addressee())
                ->setAddresseeParcelRef($request->orderReference)
                ->setAddress($recipientAddress);

            $letter = (new Letter())
                ->setService($service)
                ->setParcel($parcel)
                ->setSender($sender)
                ->setAddressee($addressee);

            $outputFormatConfig = $this->config['output_format'] ?? [];
            $outputFormat = (new OutputFormat())
                ->setX((int) ($outputFormatConfig['x'] ?? 0))
                ->setY((int) ($outputFormatConfig['y'] ?? 0))
                ->setOutputPrintingType((string) ($outputFormatConfig['output_printing_type'] ?? 'PDF_A4_300dpi'));

            $labelRequest = (new GenerateLabelRequest())
                ->setContractNumber($contractNumber)
                ->setPassword($password)
                ->setOutputFormat($outputFormat)
                ->setLetter($letter);

            $result = $generate->generateLabel(new GenerateLabel($labelRequest));

            if ($result === false) {
                $lastError = $generate->getLastError();
                $message = 'Colissimo generateLabel failed.';
                if (is_array($lastError) && $lastError !== []) {
                    $first = reset($lastError);
                    if ($first instanceof Throwable) {
                        $message .= ' '.$first->getMessage();
                    }
                }
                throw new RuntimeException($message);
            }

            $response = $generate->getResult();
            $labelResponse = $response?->getLabelResponse();

            if ($labelResponse === null) {
                throw new RuntimeException('Colissimo returned no labelResponse.');
            }

            $trackingNumber = (string) ($labelResponse->getParcelNumber() ?? '');
            $labelPdf = (string) ($labelResponse->getLabel() ?? '');

            if ($trackingNumber === '') {
                throw new RuntimeException('Colissimo did not return a tracking number.');
            }

            return new ShipmentResponse(
                trackingNumber: $trackingNumber,
                labelPdfBase64: base64_encode($labelPdf),
                rawResponse: [
                    'tracking_number' => $trackingNumber,
                    'pdf_url' => $labelResponse->getPdfUrl(),
                ],
            );
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException('Colissimo SOAP call failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function testCredentials(): bool
    {
        $credentials = $this->config['credentials'] ?? [];

        return (string) ($credentials['contract_number'] ?? '') !== ''
            && (string) ($credentials['password'] ?? '') !== '';
    }
}

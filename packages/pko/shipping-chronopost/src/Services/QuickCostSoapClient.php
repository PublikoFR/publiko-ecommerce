<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Services;

use Pko\ShippingChronopost\Exceptions\QuickCostException;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * Pure SOAP client for Chronopost QuickcostServiceWS.
 *
 * Responsibility limited to the SOAP call + response parsing. Caching and
 * fallback policy live in Pko\ShippingCommon\Pricing\LivePricingResolver.
 *
 * Official WSDL: https://ws.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl
 */
class QuickCostSoapClient
{
    public const DEFAULT_WSDL = 'https://ws.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl';

    /**
     * @param  array{account?: string, password?: string, sub_account?: string}  $credentials
     */
    public function __construct(
        protected array $credentials,
        protected ?string $wsdl = null,
        protected ?SoapClient $client = null,
        protected int $timeoutSeconds = 5,
    ) {}

    public function quickCost(
        string $serviceCode,
        float $weightKg,
        string $depZip,
        string $arrZip,
        string $depCountry = 'FR',
        string $arrCountry = 'FR',
    ): QuickCostResponse {
        $account = (string) ($this->credentials['account'] ?? '');
        $password = (string) ($this->credentials['password'] ?? '');

        if ($account === '' || $password === '') {
            throw QuickCostException::missingCredentials();
        }

        try {
            $client = $this->client ?? $this->buildSoapClient();

            $response = $client->quickCost([
                'accountNumber' => $account,
                'password' => $password,
                'depCode' => $depZip,
                'arrCode' => $arrZip,
                'weight' => max(0.01, $weightKg),
                'productCode' => $serviceCode,
                'type' => 'M',
                'depCountry' => $depCountry,
                'arrCountry' => $arrCountry,
            ]);
        } catch (SoapFault $e) {
            throw QuickCostException::soapFailure($e->getMessage(), $e);
        } catch (Throwable $e) {
            throw QuickCostException::soapFailure($e->getMessage(), $e);
        }

        return $this->parseResponse($response, $serviceCode);
    }

    protected function buildSoapClient(): SoapClient
    {
        return new SoapClient($this->wsdl ?? self::DEFAULT_WSDL, [
            'trace' => false,
            'exceptions' => true,
            'connection_timeout' => $this->timeoutSeconds,
            'cache_wsdl' => WSDL_CACHE_BOTH,
        ]);
    }

    protected function parseResponse(mixed $response, string $requestedService): QuickCostResponse
    {
        $payload = $response->return ?? $response;

        $errorCode = (string) ($payload->errorCode ?? '0');
        if ($errorCode !== '0' && $errorCode !== '') {
            $message = (string) ($payload->errorMessage ?? 'unknown');
            throw QuickCostException::apiError($errorCode, $message);
        }

        // The Chronopost WSDL is known to return slightly different field names
        // depending on the service contract. Accept the usual variants.
        $priceTTC = $payload->reservedAmountInclTaxe
            ?? $payload->amountTTC
            ?? $payload->amount
            ?? null;
        $priceHT = $payload->reservedAmountExclTaxe
            ?? $payload->amountHT
            ?? null;

        if ($priceTTC === null) {
            throw QuickCostException::apiError('parse', 'quickCost response missing amount field');
        }

        return new QuickCostResponse(
            serviceCode: (string) ($payload->productCode ?? $requestedService),
            priceCentsTTC: (int) round(((float) $priceTTC) * 100),
            priceCentsHT: (int) round(((float) ($priceHT ?? $priceTTC)) * 100),
            currency: (string) ($payload->currency ?? 'EUR'),
        );
    }
}

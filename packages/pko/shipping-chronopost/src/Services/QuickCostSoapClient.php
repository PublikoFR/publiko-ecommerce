<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Services;

use Pko\ShippingChronopost\Exceptions\QuickCostException;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * Pure SOAP client for Chronopost QuickcostServiceWS (opération `quickCostV3`).
 *
 * Responsibility limited to the SOAP call + response parsing. Caching and
 * fallback policy live in Pko\ShippingCommon\Pricing\LivePricingResolver.
 *
 * Contrat (doc Web Services VL3.25.10.10 §2.7.4 + WSDL) :
 *   - requête : accountNumber, password, depCode, arrCode, weight, productCode, type.
 *     `depCode` / `arrCode` = code postal en France, **code pays ISO-2** à
 *     l'international. Aucun champ pays séparé n'existe.
 *   - réponse : `amount` = **HT**, `amountTTC`, `amountTVA`, `zone`, + suppléments
 *     (`service[]`) non additionnés ici. Prix contrat marchand, pas prix public.
 *   - `quickCostV3` = même requête que `quickCost` (v1, non documentée) et réponse
 *     sur-ensemble (ajoute `cap`) : on appelle la version documentée.
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

    /**
     * @param  string  $productCode  code produit Chronopost (1, 86, 17, 44…), pas le slug interne
     * @param  string  $depCode  code postal de départ (ou code pays ISO-2 hors France)
     * @param  string  $arrCode  code postal d'arrivée (ou code pays ISO-2 hors France)
     */
    public function quickCost(
        string $productCode,
        float $weightKg,
        string $depCode,
        string $arrCode,
    ): QuickCostResponse {
        $account = (string) ($this->credentials['account'] ?? '');
        $password = (string) ($this->credentials['password'] ?? '');

        if ($account === '' || $password === '') {
            throw QuickCostException::missingCredentials();
        }

        try {
            $client = $this->client ?? $this->buildSoapClient();

            $response = $client->quickCostV3([
                'accountNumber' => $account,
                'password' => $password,
                'depCode' => $depCode,
                'arrCode' => $arrCode,
                'weight' => max(0.01, $weightKg),
                'productCode' => $productCode,
                'type' => 'M',
            ]);
        } catch (SoapFault $e) {
            throw QuickCostException::soapFailure($e->getMessage(), $e);
        } catch (Throwable $e) {
            throw QuickCostException::soapFailure($e->getMessage(), $e);
        }

        return $this->parseResponse($response, $productCode);
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

    protected function parseResponse(mixed $response, string $productCode): QuickCostResponse
    {
        $payload = $response->return ?? $response;

        $errorCode = (int) ($payload->errorCode ?? 0);
        if ($errorCode !== 0) {
            throw QuickCostException::apiError($errorCode, (string) ($payload->errorMessage ?? ''));
        }

        $amountHT = isset($payload->amount) ? (float) $payload->amount : 0.0;

        // L'API répond errorCode=0 avec des montants à 0.0 quand le compte n'a pas
        // de tarif pour ce produit (constaté sur le compte de test officiel). Un 0
        // ne doit jamais devenir une livraison offerte : on lève pour que le
        // resolver retombe sur la grille (live_with_fallback) ou masque le service.
        if ($amountHT <= 0.0) {
            throw QuickCostException::amountNotFound($productCode);
        }

        $amountTVA = isset($payload->amountTVA) ? (float) $payload->amountTVA : 0.0;
        $amountTTC = isset($payload->amountTTC) ? (float) $payload->amountTTC : $amountHT + $amountTVA;

        $zone = isset($payload->zone) ? (string) $payload->zone : null;

        return new QuickCostResponse(
            serviceCode: $productCode,
            priceCentsHT: (int) round($amountHT * 100),
            priceCentsTTC: (int) round($amountTTC * 100),
            priceCentsTVA: (int) round($amountTVA * 100),
            currency: 'EUR',
            zone: $zone !== '' ? $zone : null,
        );
    }
}

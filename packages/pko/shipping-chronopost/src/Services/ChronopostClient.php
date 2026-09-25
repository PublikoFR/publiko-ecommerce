<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Services;

use Illuminate\Support\Str;
use ladromelaboratoire\chronopostws\chronopost;
use Pko\ShippingChronopost\Sdk\RefValue;
use Pko\ShippingChronopost\Sdk\Shipment;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Dto\QuoteResponse;
use Pko\ShippingCommon\Dto\ShipmentRequest;
use Pko\ShippingCommon\Dto\ShipmentResponse;
use Pko\ShippingCommon\Pricing\LivePricingResolver;
use Pko\ShippingCommon\Pricing\PricingMode;
use Pko\ShippingCommon\Pricing\PricingModeResolver;
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\ShippingCommon\Support\CarrierProductCodeResolver;
use RuntimeException;
use SoapClient;
use Throwable;

class ChronopostClient implements CarrierClient
{
    public const SHIPPING_WSDL = 'https://ws.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl';

    /** Produits livrés en point relais : `refValue.idRelais` obligatoire. */
    public const RELAY_PRODUCT_CODES = ['86'];

    /** Mêmes règles que `wsregex::__reg_PhoneNumber` / `__reg_Email` du SDK. */
    private const PHONE_PATTERN = '/^(\+[1-9][0-9]{0,2}|[0]{1})[1-9][0-9]{7,12}$/';

    private const EMAIL_PATTERN = "/^[A-Za-z0-9]+[A-Za-z0-9\/\-!&'*+%$#=?^_`{|}~\.]*@(?:[a-z0-9\-]+\.)+[a-z]{2,12}$/";

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ?LivePricingResolver $livePricing = null,
        private readonly ?PricingModeResolver $modes = null,
        private readonly ?QuickCostSoapClient $liveClient = null,
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
        $maxWeight = (float) ($this->config['max_weight_kg'] ?? 30);
        if ($request->weightKg > $maxWeight) {
            return [];
        }

        // Route via LivePricingResolver when available (production), otherwise
        // fall back to the historical grid lookup on $this->config (unit tests).
        if ($this->livePricing !== null && $this->modes !== null && $this->liveClient !== null) {
            $mode = $this->modes->getFor('chronopost');
            if ($mode !== PricingMode::GRID) {
                $depZip = (string) ($this->config['shipper']['zip'] ?? '');

                return $this->livePricing->resolveLive(
                    carrier: 'chronopost',
                    request: $request,
                    livePricer: function (string $service, float $weightKg, string $dep, string $arr) {
                        // QuickCost attend le code produit Chronopost (1, 86…), pas notre
                        // slug interne (chrono13…) — même traduction que pour les étiquettes.
                        $productCode = app(CarrierProductCodeResolver::class)->resolve('chronopost', $service);
                        $resp = $this->liveClient->quickCost($productCode, $weightKg, $dep, $arr);

                        // Le montant injecté doit avoir la même nature que les grilles :
                        // ShippingCalculator ajoute la TVA en base `ht` (défaut) et la
                        // ventile en base `ttc`. Injecter le TTC en base `ht` = double TVA.
                        $priceCents = ShippingSettings::taxPriceBase() === 'ttc'
                            ? $resp->priceCentsTTC
                            : $resp->priceCentsHT;

                        return new QuoteResponse(
                            serviceCode: $service,
                            serviceLabel: $this->serviceLabel($service),
                            priceCents: $priceCents,
                            currencyCode: $resp->currency,
                        );
                    },
                    depZip: $depZip,
                );
            }

            return $this->livePricing->resolveFromGrid('chronopost', $request);
        }

        return $this->quoteFromConfigArray($request);
    }

    /**
     * Legacy grid lookup using the $config array (used by unit tests that build
     * the Client without the resolver stack).
     *
     * @return list<QuoteResponse>
     */
    private function quoteFromConfigArray(QuoteRequest $request): array
    {
        $grid = $this->config['grid'] ?? [];

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

    private function serviceLabel(string $code): string
    {
        $services = $this->config['services'] ?? [];

        return (string) ($services[$code]['label'] ?? $code);
    }

    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        $labelsData = $this->buildLabelsData($request);
        $soapClient = $this->makeShippingSoapClient();
        $client = $this->makeSdk($soapClient);

        try {
            $client->makeShippingLabels($labelsData, true, false);
        } catch (Throwable $e) {
            throw new RuntimeException('Chronopost shippingMultiParcelV4 : '.$this->describeFailure($e, $soapClient), 0, $e);
        }

        $skybill = $client->shipment->skybillValue[0] ?? null;
        $trackingNumber = (string) ($skybill->skybillNumber ?? '');

        if ($trackingNumber === '') {
            throw new RuntimeException('Chronopost did not return a tracking number.');
        }

        // `getReservedSkybillWithTypeAndMode` renvoie le PDF DÉJÀ encodé en base64
        // (vérifié sur le compte test le 2026-09-25 : la chaîne commence par « JVBER »).
        // Le ré-encoder donnait un fichier .pdf contenant du texte base64, illisible.
        $labels = $client->shipment->labels ?? [];
        $labelBase64 = '';
        foreach ($labels as $label) {
            if (is_string($label) && $label !== '') {
                $labelBase64 = str_starts_with($label, '%PDF') ? base64_encode($label) : $label;
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

    /**
     * Tableau passé à `chronopost::makeShippingLabels()`, calqué sur l'exemple officiel
     * `shippingMultiParcelV4` (doc Web Services VL3.25.10.10).
     *
     * Le SDK valide chaque champ par regex (`wsregex`) et instancié avec
     * `useExceptions = true`, il lève au premier écart : les valeurs sont donc
     * normalisées ici (ASCII, longueurs, téléphones en chiffres, entiers là où le SDK
     * exige un int). Seules les clés disposant d'un setter SDK sont envoyées — une clé
     * inconnue lève aussi (`evtCode`, `as`, `version` « 2.0 » ont été retirés pour ça ;
     * `DC` et la version 2.0 sont de toute façon les défauts).
     *
     * @return array<string, mixed>
     */
    public function buildLabelsData(ShipmentRequest $request): array
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
        $productCode = $this->normalizeProductCode($request->productCode());
        $isRelay = in_array($productCode, self::RELAY_PRODUCT_CODES, true);

        $civility = strtoupper((string) ($shipper['civility'] ?? 'M'));
        $civility = in_array($civility, ['E', 'L', 'M'], true) ? $civility : 'M';

        $shipperPhone = $this->phone($shipper['phone'] ?? null);
        $shipperEmail = $this->email($shipper['email'] ?? null);
        if ($shipperPhone === null || $shipperEmail === null) {
            throw new RuntimeException('Chronopost : téléphone ou e-mail expéditeur absent ou invalide (SHIPPER_PHONE / SHIPPER_EMAIL).');
        }

        $recipientPhone = $this->phone($recipient['phone'] ?? null);
        if ($recipientPhone === null) {
            throw new RuntimeException(sprintf(
                'Chronopost : téléphone destinataire absent ou invalide (« %s »).',
                (string) ($recipient['phone'] ?? ''),
            ));
        }

        $idRelais = null;
        if ($isRelay) {
            $idRelais = strtoupper(trim((string) $request->pickupPointId));
            if (preg_match(RefValue::ID_RELAIS_PATTERN, $idRelais) !== 1) {
                throw new RuntimeException(sprintf(
                    'Chronopost Relais : identifiant de point relais absent ou invalide (« %s »).',
                    (string) $request->pickupPointId,
                ));
            }
        }

        [$shipperAdress1, $shipperAdress2] = $this->address($shipper['street'] ?? '');
        [$recipientAdress1, $recipientAdress2] = $this->address($recipient['street'] ?? '');
        $shipperName = $this->text($shipper['name'] ?? '', 100);
        $recipientPerson = $this->text($recipient['name'] ?? '', 100);
        $recipientCompany = $this->text($recipient['company'] ?? '', 100);

        $shipperValue = array_filter([
            'shipperAdress1' => $shipperAdress1,
            'shipperAdress2' => $shipperAdress2,
            'shipperCity' => $this->text($shipper['city'] ?? '', 50),
            'shipperCivility' => $civility,
            'shipperContactName' => $shipperName,
            'shipperCountry' => $this->country($shipper['country'] ?? null),
            'shipperEmail' => $shipperEmail,
            'shipperName' => $shipperName,
            'shipperPhone' => $shipperPhone,
            'shipperZipCode' => $this->zip($shipper['zip'] ?? ''),
            // 1 = professionnel : l'expéditeur est la boutique, jamais un particulier.
            'shipperType' => '1',
        ], fn ($v) => $v !== '');

        // customerValue = le client Chronopost titulaire du contrat, ici l'expéditeur lui-même.
        $customerValue = array_filter([
            'customerAdress1' => $shipperAdress1,
            'customerAdress2' => $shipperAdress2,
            'customerCity' => $shipperValue['shipperCity'] ?? '',
            'customerCivility' => $civility,
            'customerContactName' => $shipperName,
            'customerCountry' => $shipperValue['shipperCountry'],
            'customerEmail' => $shipperEmail,
            'customerName' => $shipperName,
            'customerPhone' => $shipperPhone,
            'customerZipCode' => $shipperValue['shipperZipCode'] ?? '',
        ], fn ($v) => $v !== '');

        // En relais, recipientName = nom du point (posé en `company` par
        // CreateCarrierShipmentJob::applyPickupPoint()) et recipientName2 = le client,
        // comme dans l'exemple officiel « Chrono RELAIS 13H ». Hors relais : raison
        // sociale puis contact.
        $recipientValue = array_filter([
            'recipientAdress1' => $recipientAdress1,
            'recipientAdress2' => $recipientAdress2,
            'recipientCity' => $this->text($recipient['city'] ?? '', 50),
            'recipientContactName' => $recipientPerson,
            'recipientCountry' => $this->country($recipient['country'] ?? null),
            // L'e-mail destinataire est obligatoire côté WS ; à défaut on met celui de
            // l'expéditeur plutôt que de bloquer l'étiquette.
            'recipientEmail' => $this->email($recipient['email'] ?? null) ?? $shipperEmail,
            'recipientName' => $recipientCompany !== '' ? $recipientCompany : $recipientPerson,
            'recipientName2' => $recipientPerson,
            // En relais, le WS attend le portable du client (SMS de mise à disposition).
            'recipientPhone' => $recipientPhone,
            'recipientMobilePhone' => $isRelay ? $recipientPhone : '',
            'recipientZipCode' => $this->zip($recipient['zip'] ?? ''),
            // 2 = particulier : valeur de l'exemple officiel Chrono Relais ; 1 = professionnel.
            'recipientType' => $isRelay ? '2' : '1',
        ], fn ($v) => $v !== '');

        $reference = $this->text($request->orderReference, 35);

        $refValue = [
            'customerSkybillNumber' => substr((string) preg_replace('/[^A-Za-z0-9]/', '', $request->orderReference), 0, 15),
            'shipperRef' => $reference,
            'recipientRef' => $reference,
        ];
        if ($idRelais !== null) {
            // Clé SDK `idRelai` (sans « s ») → wsrefvalue::setidRelai() → <idRelais>.
            $refValue['idRelai'] = $idRelais;
        }

        return [
            'headerValue' => [
                'accountNumber' => (int) $account,
                'idEmit' => 'CHRFR',
                // Le SDK exige un int et refuse un header sans sous-compte ; 0 = aucun.
                'subAccount' => (int) $subAccount,
            ],
            'shipperValue' => $shipperValue,
            'customerValue' => $customerValue,
            'recipientValue' => $recipientValue,
            'refValue' => $refValue,
            'skybillValue' => [
                'bulkNumber' => '1',
                'productCode' => $productCode,
                'service' => $this->skybillService($request),
                'shipDate' => date('Y-m-d'),
                'shipHour' => date('H'),
                'weight' => round(max(0.1, $request->weightKg), 2),
                'weightUnit' => 'KGM',
                ...($request->dimensionsCm !== null ? [
                    'length' => (int) ceil((float) $request->dimensionsCm['length']),
                    'width' => (int) ceil((float) $request->dimensionsCm['width']),
                    'height' => (int) ceil((float) $request->dimensionsCm['height']),
                ] : []),
                'objectType' => 'MAR',
                'codCurrency' => 'EUR',
                'codValue' => 0,
                'insuredCurrency' => 'EUR',
                'insuredValue' => 0,
                'customsCurrency' => 'EUR',
                'customsValue' => 0,
                'skybillRank' => '1',
                'content1' => 'Colis',
            ],
            'skybillParamsValue' => [
                'mode' => $this->config['label_format'] ?? 'PDF',
            ],
            'password' => $password,
            'modeRetour' => 2,
            'numberOfParcel' => 1,
            'multiParcel' => 'N',
        ];
    }

    /**
     * Code service du skybill : `0` = livraison en semaine (défaut), `6` = samedi pour
     * Chrono 13H / Relais 13H (colis remis le vendredi). Le samedi n'est pas proposé au
     * checkout : seul le kit de validation (`chronopost:validation-kit`) le demande.
     */
    protected function skybillService(ShipmentRequest $request): string
    {
        $service = trim((string) $request->carrierService);

        if ($service === '') {
            return '0';
        }

        if (preg_match('/^[0-9]{1,3}$/', $service) !== 1) {
            throw new RuntimeException(sprintf('Chronopost : code service « %s » invalide.', $service));
        }

        return $service;
    }

    /**
     * SDK avec `useExceptions = true` : sinon tout champ refusé par ses regex est
     * ignoré en silence et l'appel échoue plus loin sans explication.
     */
    protected function makeSdk(SoapClient $soapClient): chronopost
    {
        $sdk = new chronopost(true, false, false, false);
        // Accepte les identifiants relais réels (999AA), cf. Sdk\RefValue.
        $sdk->shipment = new Shipment(true);

        // Le SDK construit son SoapClient sans trace dès que useExceptions est actif, et
        // son message d'erreur métier (« error code $response->return->errorCode ») ne
        // s'interpole pas : PHP lève « Object of class stdClass could not be converted
        // to string » à la place. On lui fournit donc un client tracé pour pouvoir relire
        // errorCode / errorMessage dans la réponse brute. Propriété privée : le SDK se
        // contourne, il ne se patche pas.
        (function () use ($soapClient): void {
            $this->shippingSC = $soapClient;
        })->call($sdk);

        return $sdk;
    }

    protected function makeShippingSoapClient(): SoapClient
    {
        return new SoapClient(self::SHIPPING_WSDL, $this->shippingSoapOptions());
    }

    /**
     * @return array<string, mixed>
     */
    protected function shippingSoapOptions(): array
    {
        return [
            'soap_version' => SOAP_1_2,
            'encoding' => 'UTF-8',
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 10,
            'cache_wsdl' => WSDL_CACHE_BOTH,
        ];
    }

    /**
     * Message d'échec lisible (stocké dans pko_carrier_shipments.error_message). Le
     * mot de passe n'apparaît dans aucun message du SDK (setpassword ne valide rien).
     */
    private function describeFailure(Throwable $e, SoapClient $soapClient): string
    {
        $raw = null;
        try {
            $raw = $soapClient->__getLastResponse();
        } catch (Throwable) {
            // Pas de réponse : échec avant l'appel réseau (validation SDK, WSDL…).
        }

        if (is_string($raw)
            && preg_match('#<errorCode>\s*(\d+)\s*</errorCode>#', $raw, $code) === 1
            && $code[1] !== '0') {
            $message = preg_match('#<errorMessage>(.*?)</errorMessage>#s', $raw, $m) === 1
                ? html_entity_decode(trim($m[1]))
                : '';

            return sprintf('erreur %s%s', $code[1], $message !== '' ? ' — '.$message : '');
        }

        return $e->getMessage();
    }

    /**
     * Le WS exige deux caractères (« 1 » → erreur 33) et la regex du SDK refuse les
     * codes à un chiffre : on complète à gauche si la base porte encore l'ancienne forme.
     */
    private function normalizeProductCode(string $code): string
    {
        $code = strtoupper(trim($code));

        return strlen($code) === 1 ? str_pad($code, 2, '0', STR_PAD_LEFT) : $code;
    }

    /**
     * Texte au format WS `[a-zA-Z0-9 ]` : translittération ASCII, ponctuation → espace.
     */
    private function text(mixed $value, int $max): string
    {
        $ascii = Str::ascii((string) $value);
        $clean = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^A-Za-z0-9 ]/', ' ', $ascii)));

        return trim(substr($clean, 0, $max));
    }

    /**
     * Adresse sur deux lignes de 38 caractères (limite WS), coupée entre deux mots.
     *
     * @return array{0: string, 1: string}
     */
    private function address(mixed $street): array
    {
        $lines = explode("\n", wordwrap($this->text($street, 76), 38, "\n", true));

        return [$lines[0] ?? '', trim(substr($lines[1] ?? '', 0, 38))];
    }

    private function zip(mixed $zip): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $zip), 0, 9);
    }

    private function country(mixed $code): string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : 'FR';
    }

    /**
     * Téléphone en chiffres (`0XXXXXXXXX` ou `+CCXXXXXXXX`), sinon null.
     *
     * Un numéro déjà au format international est conservé tel quel : certains pays
     * gardent un 0 significatif après l'indicatif (Italie : +39 06…). Le préfixe
     * national n'est retiré que lorsqu'il est identifié sans ambiguïté : notation
     * explicite « (0) » après un indicatif, ou « +330 » (la France n'a aucun numéro
     * commençant par 0 après son indicatif). Aucune suppression générique.
     */
    private function phone(mixed $phone): ?string
    {
        $raw = (string) preg_replace('/^\s*(\+|00)\s*([1-9][\d\s.\-]*?)\s*\(\s*0\s*\)/', '$1$2', (string) $phone);
        $digits = (string) preg_replace('/[^0-9+]/', '', $raw);
        $digits = (string) preg_replace('/^00/', '+', $digits);
        $digits = (string) preg_replace('/^\+330(?=[1-9])/', '+33', $digits);

        return preg_match(self::PHONE_PATTERN, $digits) === 1 ? $digits : null;
    }

    private function email(mixed $email): ?string
    {
        $email = trim((string) $email);

        return preg_match(self::EMAIL_PATTERN, $email) === 1 ? $email : null;
    }

    public function testCredentials(): bool
    {
        $credentials = $this->config['credentials'] ?? [];

        return (string) ($credentials['account'] ?? '') !== ''
            && (string) ($credentials['password'] ?? '') !== '';
    }
}

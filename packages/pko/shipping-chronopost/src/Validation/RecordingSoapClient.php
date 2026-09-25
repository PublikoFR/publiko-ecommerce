<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Validation;

use SoapClient;

/**
 * SoapClient qui consigne chaque requête / réponse brute dans un SoapExchangeLog.
 *
 * `__getLastRequest()` ne garde que le dernier appel : or `createShipment()` enchaîne
 * `shippingMultiParcelV4` puis `getReservedSkybillWithTypeAndMode`, et le kit doit
 * fournir les deux.
 */
class RecordingSoapClient extends SoapClient
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(?string $wsdl, array $options, private readonly SoapExchangeLog $log)
    {
        parent::__construct($wsdl, $options);
    }

    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): ?string
    {
        $response = parent::__doRequest($request, $location, $action, $version, $oneWay);

        $this->log->record($request, (string) $response);

        return $response;
    }
}

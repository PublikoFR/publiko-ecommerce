<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Validation;

use Pko\ShippingChronopost\Services\ChronopostClient;
use Pko\ShippingChronopost\Services\PickupPointSoapClient;
use SoapClient;

/**
 * Construit les VRAIS clients Chronopost (ChronopostClient, PickupPointSoapClient)
 * pour le kit de validation, avec un transport SOAP enregistreur. Seul le SoapClient
 * change : payload, normalisation et lecture de la réponse restent ceux de la prod.
 *
 * Les tests remplacent cette fabrique (ou `soapClient()`) pour n'appeler aucun réseau.
 */
class ValidationKitClientFactory
{
    /**
     * @param  array<string, mixed>  $config  Même forme que config('chronopost').
     */
    public function shipping(array $config, SoapExchangeLog $log): ChronopostClient
    {
        return new class($config, $this, $log) extends ChronopostClient
        {
            /**
             * @param  array<string, mixed>  $config
             */
            public function __construct(
                array $config,
                private readonly ValidationKitClientFactory $factory,
                private readonly SoapExchangeLog $log,
            ) {
                parent::__construct($config);
            }

            protected function makeShippingSoapClient(): SoapClient
            {
                return $this->factory->soapClient(self::SHIPPING_WSDL, $this->shippingSoapOptions(), $this->log);
            }
        };
    }

    /**
     * @param  array{account: string, password: string}  $credentials
     */
    public function pickup(array $credentials, SoapExchangeLog $log): PickupPointSoapClient
    {
        return new PickupPointSoapClient(
            credentials: $credentials,
            client: $this->soapClient(PickupPointSoapClient::DEFAULT_WSDL, [
                'trace' => true,
                'exceptions' => true,
                'connection_timeout' => 15,
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'stream_context' => stream_context_create(['http' => ['timeout' => 15]]),
            ], $log),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function soapClient(string $wsdl, array $options, SoapExchangeLog $log): SoapClient
    {
        return new RecordingSoapClient($wsdl, $options, $log);
    }
}

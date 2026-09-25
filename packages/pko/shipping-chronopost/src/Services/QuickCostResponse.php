<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Services;

/**
 * Tarif renvoyé par QuickcostServiceWS pour un colis.
 *
 * Prix contrat marchand (négocié sur le compte appelant), pas un prix public.
 * `amount` de l'API = HT ; `amountTTC` et `amountTVA` complètent.
 */
final class QuickCostResponse
{
    public function __construct(
        public readonly string $serviceCode,
        public readonly int $priceCentsHT,
        public readonly int $priceCentsTTC,
        public readonly int $priceCentsTVA,
        public readonly string $currency = 'EUR',
        public readonly ?string $zone = null,
    ) {}
}

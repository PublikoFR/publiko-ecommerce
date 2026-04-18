<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Dto;

final class QuoteRequest
{
    /**
     * @param  list<string>  $serviceCodes  subset of services to quote; empty = all enabled in config
     */
    public function __construct(
        public readonly float $weightKg,
        public readonly string $destinationPostcode,
        public readonly string $destinationCountry,
        public readonly array $serviceCodes = [],
    ) {}
}

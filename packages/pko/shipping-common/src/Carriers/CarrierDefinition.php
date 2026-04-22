<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Carriers;

final class CarrierDefinition
{
    /**
     * @param  array<string, string>  $credentialLabels  logical key => human label (used by the Filament form)
     * @param  array<string, mixed>  $meta  free-form metadata (e.g. max_weight_kg, wsdl_url)
     */
    public function __construct(
        public readonly string $code,
        public readonly string $displayName,
        public readonly string $icon,
        public readonly string $clientServiceId,
        public readonly string $secretsModule,
        public readonly array $credentialLabels,
        public readonly ?string $configPageClass = null,
        public readonly int $navigationSort = 20,
        public readonly array $meta = [],
        public readonly bool $supportsLive = false,
    ) {}
}

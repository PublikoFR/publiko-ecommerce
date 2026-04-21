<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Carriers;

class CarrierRegistry
{
    /**
     * @var array<string, CarrierDefinition>
     */
    protected array $carriers = [];

    public function register(CarrierDefinition $carrier): void
    {
        $this->carriers[$carrier->code] = $carrier;
    }

    public function get(string $code): ?CarrierDefinition
    {
        return $this->carriers[$code] ?? null;
    }

    public function has(string $code): bool
    {
        return isset($this->carriers[$code]);
    }

    /**
     * @return array<string, CarrierDefinition>
     */
    public function all(): array
    {
        return $this->carriers;
    }

    /**
     * @return array<int, string>
     */
    public function codes(): array
    {
        return array_keys($this->carriers);
    }

    /**
     * @return array<int, class-string>
     */
    public function configPageClasses(): array
    {
        return array_values(array_filter(array_map(
            fn (CarrierDefinition $c): ?string => $c->configPageClass,
            $this->carriers,
        )));
    }
}

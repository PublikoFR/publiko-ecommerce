<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Repositories;

use Illuminate\Support\Facades\Cache;
use Pko\ShippingCommon\Models\CarrierGridBracket;

class CarrierGridRepository
{
    protected const CACHE_KEY_PREFIX = 'pko.shipping.grid.';

    protected const CACHE_TTL = 3600;

    /**
     * @return array<int, array{max_kg: int, price: int, service_code: string|null}>
     */
    public function forCarrier(string $carrierCode, ?string $serviceCode = null): array
    {
        $all = $this->allFor($carrierCode);

        if ($serviceCode === null) {
            return array_values(array_filter($all, fn (array $b): bool => $b['service_code'] === null));
        }

        return array_values(array_filter(
            $all,
            fn (array $b): bool => $b['service_code'] === $serviceCode || $b['service_code'] === null,
        ));
    }

    /**
     * @return array<int, array{max_kg: int, price: int, service_code: string|null}>
     */
    public function allFor(string $carrierCode): array
    {
        return Cache::remember(
            self::CACHE_KEY_PREFIX.$carrierCode,
            self::CACHE_TTL,
            fn (): array => CarrierGridBracket::query()
                ->where('carrier_code', $carrierCode)
                ->orderBy('service_code')
                ->orderBy('max_kg')
                ->get(['service_code', 'max_kg', 'price_cents'])
                ->map(fn (CarrierGridBracket $b): array => [
                    'service_code' => $b->service_code,
                    'max_kg' => (int) $b->max_kg,
                    'price' => (int) $b->price_cents,
                ])
                ->toArray(),
        );
    }

    public function flushCache(?string $carrierCode = null): void
    {
        if ($carrierCode !== null) {
            Cache::forget(self::CACHE_KEY_PREFIX.$carrierCode);

            return;
        }

        Cache::flush();
    }
}

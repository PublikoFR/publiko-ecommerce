<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Repositories;

use Illuminate\Support\Facades\Cache;
use Pko\ShippingCommon\Models\CarrierService;

class CarrierServiceRepository
{
    protected const CACHE_KEY_PREFIX = 'pko.shipping.services.';

    protected const CACHE_TTL = 3600;

    /**
     * @return array<int, array{code: string, label: string, enabled: bool}>
     */
    public function enabledFor(string $carrierCode): array
    {
        return array_values(array_filter($this->allFor($carrierCode), fn (array $s) => $s['enabled']));
    }

    /**
     * @return array<int, array{code: string, label: string, enabled: bool}>
     */
    public function allFor(string $carrierCode): array
    {
        return Cache::remember(
            self::CACHE_KEY_PREFIX.$carrierCode,
            self::CACHE_TTL,
            fn (): array => CarrierService::query()
                ->where('carrier_code', $carrierCode)
                ->orderBy('sort')
                ->orderBy('service_code')
                ->get(['service_code', 'label', 'enabled'])
                ->map(fn (CarrierService $s): array => [
                    'code' => (string) $s->service_code,
                    'label' => (string) $s->label,
                    'enabled' => (bool) $s->enabled,
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

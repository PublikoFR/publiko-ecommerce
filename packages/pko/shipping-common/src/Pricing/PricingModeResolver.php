<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pricing;

use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\StorefrontCms\Models\Setting;
use Throwable;

class PricingModeResolver
{
    public function __construct(
        protected CarrierRegistry $carriers,
    ) {}

    public function getFor(string $carrier): PricingMode
    {
        $def = $this->carriers->get($carrier);
        if ($def === null || ! $def->supportsLive) {
            return PricingMode::GRID;
        }

        try {
            $stored = Setting::get($this->settingKey($carrier));
        } catch (Throwable) {
            return PricingMode::GRID;
        }

        if (is_string($stored) && ($case = PricingMode::tryFrom($stored)) !== null) {
            return $case;
        }

        return PricingMode::GRID;
    }

    public function setFor(string $carrier, PricingMode $mode): void
    {
        Setting::set($this->settingKey($carrier), $mode->value);
    }

    protected function settingKey(string $carrier): string
    {
        return "shipping.{$carrier}.pricing_mode";
    }
}

<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Modifiers;

use Closure;
use Lunar\Base\ShippingModifier;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Facades\Taxes;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Contracts\CarrierClient;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Pko\ShippingCommon\Support\WeightCalculator;
use Pko\ShippingCommon\Support\ZoneResolver;

/**
 * Template shipping modifier for PKO carriers.
 *
 * Subclass declares only carrierCode(). Parent handles:
 *   - address / country / postcode extraction
 *   - zone filtering (default: France métropolitaine)
 *   - weight computation
 *   - quote call
 *   - injection of ShippingOption entries into the manifest
 *
 * Override shouldQuote() to implement a different zone policy.
 */
abstract class AbstractCarrierModifier extends ShippingModifier
{
    abstract protected function carrierCode(): string;

    public function handle(Cart $cart, Closure $next)
    {
        $address = $cart->shippingAddress;
        if ($address === null) {
            return $next($cart);
        }

        $country = $address->country?->iso2 ?? 'FR';
        $postcode = (string) ($address->postcode ?? '');

        if (! $this->shouldQuote($country, $postcode)) {
            return $next($cart);
        }

        $weightKg = WeightCalculator::fromCartTaxable($cart);
        if ($weightKg <= 0) {
            return $next($cart);
        }

        $quotes = $this->resolveClient()->quote(new QuoteRequest(
            weightKg: $weightKg,
            destinationPostcode: $postcode,
            destinationCountry: $country,
        ));

        if ($quotes === []) {
            return $next($cart);
        }

        $currency = $cart->currency ?? Currency::getDefault();
        $taxClass = TaxClass::getDefault();
        $displayName = $this->carrierDisplayName();

        // Base de taxe des prix de grille (cf. ShippingSettings::taxPriceBase()).
        // 'ttc' → reconvertir en net via le taux réel pour garder la TVA ventilée.
        $priceBase = \Pko\ShippingCommon\Settings\ShippingSettings::taxPriceBase();
        $taxRate = ($priceBase === 'ttc' && $taxClass !== null)
            ? $this->effectiveTaxRate($taxClass, $address, $currency)
            : 0.0;

        foreach ($quotes as $quote) {
            $netCents = $priceBase === 'ttc'
                ? $this->grossToNet($quote->priceCents, $taxRate)
                : $quote->priceCents;

            ShippingManifest::addOption(new ShippingOption(
                name: $displayName.' — '.$quote->serviceLabel,
                description: 'Livraison '.$quote->serviceLabel.' (France métropolitaine)',
                identifier: $this->carrierCode().'.'.$quote->serviceCode,
                price: new Price($netCents, $currency, 1),
                taxClass: $taxClass,
                meta: [
                    'carrier' => $this->carrierCode(),
                    'service_code' => $quote->serviceCode,
                    'weight_kg' => $weightKg,
                ],
            ));
        }

        return $next($cart);
    }

    /**
     * Taux de TVA effectif d'une TaxClass pour l'adresse/devise courantes.
     *
     * Sonde le moteur de taxe Lunar sur une base connue (zone-aware, FR métropole
     * en v1) et en déduit le taux. Retourne 0.0 si aucune taxe n'est applicable.
     */
    protected function effectiveTaxRate(TaxClass $taxClass, $address, $currency): float
    {
        $probeBase = 100000; // 1 000,00 € — base large pour limiter l'erreur d'arrondi.

        $probe = new ShippingOption(
            name: 'tax-probe',
            description: null,
            identifier: 'shipping.tax.probe',
            price: new Price($probeBase, $currency, 1),
            taxClass: $taxClass,
        );

        try {
            $taxCents = (int) Taxes::setShippingAddress($address)
                ->setCurrency($currency)
                ->setPurchasable($probe)
                ->getBreakdown($probeBase)
                ->amounts
                ->sum('price.value');
        } catch (\Throwable) {
            // Zone de taxe non résolue → pas de reconversion (le prix reste tel quel).
            return 0.0;
        }

        return $probeBase > 0 ? $taxCents / $probeBase : 0.0;
    }

    /**
     * Convertit un prix TTC (cents) en prix net (cents) selon le taux fourni.
     */
    protected function grossToNet(int $grossCents, float $taxRate): int
    {
        if ($grossCents === 0 || $taxRate <= 0.0) {
            return $grossCents;
        }

        return (int) round($grossCents / (1 + $taxRate));
    }

    protected function shouldQuote(string $country, string $postcode): bool
    {
        if (ZoneResolver::isMetropole($postcode, $country)) {
            return true;
        }

        // Ouvre la Corse si un supplément auto 'corse' est actif — tous les carriers concernés.
        if (ZoneResolver::isCorse($postcode, $country)) {
            return $this->hasActiveCorseSurcharge();
        }

        return false;
    }

    private function hasActiveCorseSurcharge(): bool
    {
        return ShippingSurcharge::query()
            ->where('enabled', true)
            ->where('mode', 'auto')
            ->where(function ($q): void {
                $q->where('code', 'corse')
                    ->orWhereJsonContains('rule->type', 'corse')
                    ->orWhereJsonContains('rule->postcode_prefix', '20');
            })
            ->exists();
    }

    protected function resolveClient(): CarrierClient
    {
        return app('pko.shipping.carrier.'.$this->carrierCode());
    }

    protected function carrierDisplayName(): string
    {
        $def = app(CarrierRegistry::class)->get($this->carrierCode());

        return $def?->displayName ?? ucfirst($this->carrierCode());
    }
}

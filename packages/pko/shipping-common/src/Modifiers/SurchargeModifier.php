<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Modifiers;

use Closure;
use Lunar\Base\ShippingManifestInterface;
use Lunar\Base\ShippingModifier;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Pko\ShippingCommon\Support\ZoneResolver;

/**
 * Applique les suppléments transport (table pko_shipping_surcharges) au manifest.
 *
 * Modes :
 *   auto   — majore le prix de chaque option carrier présente dans le manifest.
 *   quote  — injecte une option sentinel (price=0, meta.quote=true) à la place du prix.
 *   rebill — hors flux panier (refacturation a posteriori) : aucune injection, ignoré ici.
 *
 * Doit être enregistré APRÈS FrancoModifier pour que les prix carriers soient déjà
 * éventuellement nuls (franco) avant application des suppléments.
 */
class SurchargeModifier extends ShippingModifier
{
    public function handle(Cart $cart, Closure $next)
    {
        $address = $cart->shippingAddress;

        if ($address === null) {
            return $next($cart);
        }

        $postcode = (string) ($address->postcode ?? '');
        $country = (string) ($address->country?->iso2 ?? 'FR');

        $surcharges = ShippingSurcharge::query()
            ->where('enabled', true)
            ->whereIn('mode', ['auto', 'quote'])
            ->get();

        if ($surcharges->isEmpty()) {
            return $next($cart);
        }

        $currency = $cart->currency ?? Currency::getDefault();

        foreach ($surcharges as $surcharge) {
            if (! $this->matchesAddress($surcharge, $postcode, $country)) {
                continue;
            }

            if ($surcharge->mode === 'auto') {
                $this->applyAutoSurcharge($surcharge, $currency);
            } elseif ($surcharge->mode === 'quote') {
                $this->injectQuoteOption($surcharge, $currency);
            }
        }

        return $next($cart);
    }

    private function matchesAddress(ShippingSurcharge $surcharge, string $postcode, string $country): bool
    {
        $rule = $surcharge->rule ?? [];

        if (isset($rule['type']) && $rule['type'] === 'corse') {
            return ZoneResolver::isCorse($postcode, $country);
        }

        if (isset($rule['postcode_prefix'])) {
            $normalized = preg_replace('/\s+/', '', $postcode) ?? '';

            return str_starts_with($normalized, (string) $rule['postcode_prefix']);
        }

        return false;
    }

    private function applyAutoSurcharge(ShippingSurcharge $surcharge, Currency $currency): void
    {
        $manifest = app(ShippingManifestInterface::class);

        $manifest->options = $manifest->options->map(function (ShippingOption $option) use ($surcharge, $currency) {
            // Ne majore pas les options qui sont déjà des sentinels (sur devis, franco…).
            if ($option->meta['quote'] ?? false) {
                return $option;
            }

            return new ShippingOption(
                name: $option->name,
                description: $option->description,
                identifier: $option->getIdentifier(),
                price: new Price($option->price->value + $surcharge->amount_cents, $currency, 1),
                taxClass: $option->taxClass,
                meta: array_merge($option->meta ?? [], ['surcharge_code' => $surcharge->code]),
            );
        });
    }

    private function injectQuoteOption(ShippingSurcharge $surcharge, Currency $currency): void
    {
        $manifest = app(ShippingManifestInterface::class);
        $taxClass = TaxClass::getDefault();

        $manifest->addOption(new ShippingOption(
            name: $surcharge->label,
            description: 'Transport sur devis — prix communiqué après validation de commande',
            identifier: 'surcharge.'.$surcharge->code,
            price: new Price(0, $currency, 1),
            taxClass: $taxClass,
            meta: ['quote' => true, 'surcharge_code' => $surcharge->code],
        ));
    }
}

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
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\ShippingCommon\Support\WeightCalculator;

/**
 * Rend gratuits les services franco configurés (ShippingSettings::francoServices())
 * quand le panier atteint le seuil HT (ShippingSettings::thresholdCents()).
 *
 * Deux bases de calcul (ShippingSettings::francoBasis()) :
 *   - eligible_only : seules les lignes franco-éligibles comptent ;
 *                     une ligne exclue bloque le franco.
 *   - cart_total    : toutes les lignes comptent, aucune ligne n'est bloquante.
 *
 * Doit être enregistré APRÈS les AbstractCarrierModifier pour que les options
 * transporteur soient déjà dans le manifest.
 */
class FrancoModifier extends ShippingModifier
{
    /**
     * Constante de rétro-compatibilité (identifier par défaut du service franco).
     * Ne plus utiliser dans la logique franco — passer par ShippingSettings::francoServices().
     */
    public const CHRONO13_IDENTIFIER = 'chronopost.chrono13';

    public function handle(Cart $cart, Closure $next): mixed
    {
        $threshold = ShippingSettings::thresholdCents();
        $basis = ShippingSettings::francoBasis();
        $francoServices = ShippingSettings::francoServices();

        if ($basis === 'cart_total') {
            $shouldApply = WeightCalculator::cartSubtotalHt($cart) >= $threshold;
        } else {
            $shouldApply = WeightCalculator::francoEligibleSubtotalHt($cart) >= $threshold
                && ! WeightCalculator::cartHasFrancoExcludedLine($cart);
        }

        if (! $shouldApply) {
            return $next($cart);
        }

        $manifest = app(ShippingManifestInterface::class);

        foreach ($francoServices as $serviceCode) {
            $existing = $manifest->options->first(
                fn ($o) => ($o->meta['service_code'] ?? null) === $serviceCode
            );

            if ($existing === null) {
                continue;
            }

            $manifest->options = $manifest->options->reject(
                fn ($o) => ($o->meta['service_code'] ?? null) === $serviceCode
            );

            $currency = $cart->currency ?? Currency::getDefault();

            $manifest->addOption(new ShippingOption(
                name: 'Livraison standard offerte',
                description: $existing->description,
                identifier: $existing->getIdentifier(),
                price: new Price(0, $currency, 1),
                taxClass: $existing->taxClass,
                meta: array_merge($existing->meta ?? [], ['franco' => true]),
            ));
        }

        return $next($cart);
    }
}

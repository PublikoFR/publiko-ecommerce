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
use Pko\ShippingCommon\Support\WeightCalculator;

/**
 * Rend Chrono 13 gratuit quand le panier atteint 350 € HT de produits franco-éligibles
 * et qu'aucune ligne n'est exclue du franco.
 *
 * Doit être enregistré APRÈS les AbstractCarrierModifier (Chronopost, Colissimo)
 * afin que l'option chronopost.chrono13 soit déjà dans le manifest.
 */
class FrancoModifier extends ShippingModifier
{
    public const CHRONO13_IDENTIFIER = 'chronopost.chrono13';

    public function handle(Cart $cart, Closure $next)
    {
        $threshold = (int) config('shipping.franco.threshold_ht_cents', 35000);

        $eligibleHt = WeightCalculator::francoEligibleSubtotalHt($cart);
        $hasExcluded = WeightCalculator::cartHasFrancoExcludedLine($cart);

        if ($eligibleHt >= $threshold && ! $hasExcluded) {
            $manifest = app(ShippingManifestInterface::class);

            $existing = $manifest->options->first(
                fn ($o) => $o->getIdentifier() === self::CHRONO13_IDENTIFIER
            );

            if ($existing !== null) {
                // Retire l'option grille puis réinsère la version gratuite au même identifier.
                $manifest->options = $manifest->options->reject(
                    fn ($o) => $o->getIdentifier() === self::CHRONO13_IDENTIFIER
                );

                $currency = $cart->currency ?? Currency::getDefault();

                $manifest->addOption(new ShippingOption(
                    name: 'Livraison standard offerte',
                    description: $existing->description,
                    identifier: self::CHRONO13_IDENTIFIER,
                    price: new Price(0, $currency, 1),
                    taxClass: $existing->taxClass,
                    meta: array_merge($existing->meta ?? [], ['franco' => true]),
                ));
            }
        }

        return $next($cart);
    }
}

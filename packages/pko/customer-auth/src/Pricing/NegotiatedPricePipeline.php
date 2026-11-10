<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Pricing;

use Closure;
use Lunar\Base\PricingManagerInterface;
use Lunar\DataTypes\Price;
use Lunar\Models\ProductVariant;

/**
 * Applique le prix client HT (prix négocié + remise client %) à la résolution du
 * prix Lunar.
 *
 * Registré dans config/lunar/pricing.php. S'exécute pour chaque résolution de
 * prix (fiche produit, price-gate, ligne de panier, checkout, commande) → un
 * seul point d'application, cohérent partout.
 *
 * Règle : prix final = min(prix déjà résolu, prix négocié, prix remisé) — calcul
 * délégué à CustomerDiscountResolver (plus forte remise couvrant le produit,
 * produit non remisable exclu). Un prix dégressif par quantité (ou toute autre
 * règle Lunar) plus bas est donc préservé. Les promotions (discounts) s'appliquent
 * après, dans le pipeline panier. Objectif : toujours le prix le plus avantageux.
 *
 * En admin, Auth::user() est un membre du staff (pas un utilisateur Lunar) → le
 * PricingManager laisse $user à null → ce pipeline ne fait rien (sauf
 * impersonation client, qui pose un utilisateur Lunar).
 */
class NegotiatedPricePipeline
{
    public function __construct(private readonly CustomerDiscountResolver $resolver) {}

    public function handle(PricingManagerInterface $manager, Closure $next)
    {
        $variant = $manager->purchasable;

        if (! $variant instanceof ProductVariant) {
            return $next($manager);
        }

        $customerIds = $this->resolver->customerIdsFor($manager->user);

        if ($customerIds === []) {
            return $next($manager);
        }

        $breakdown = $this->resolver->breakdown(
            $variant,
            $manager->pricing->matched->price->value,
            $customerIds,
            (int) $manager->currency->id,
        );

        if ($breakdown->finalCents < $breakdown->originalCents) {
            $matched = clone $manager->pricing->matched;
            $matched->price = new Price($breakdown->finalCents, $manager->currency, 1);
            $manager->pricing->matched = $matched;
        }

        return $next($manager);
    }
}

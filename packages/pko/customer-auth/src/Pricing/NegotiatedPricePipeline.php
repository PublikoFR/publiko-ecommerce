<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Pricing;

use Closure;
use Lunar\Base\PricingManagerInterface;
use Lunar\DataTypes\Price;
use Lunar\Models\ProductVariant;
use Pko\CustomerAuth\Models\NegotiatedPrice;

/**
 * Applique le prix négocié HT d'un client à la résolution du prix Lunar.
 *
 * Registré dans config/lunar/pricing.php. S'exécute pour chaque résolution de
 * prix (fiche produit, price-gate, ligne de panier, checkout, commande) → un
 * seul point d'application, cohérent partout.
 *
 * Règle : on ne remplace le prix Lunar que si le prix négocié est STRICTEMENT
 * inférieur au prix déjà résolu. Ainsi un prix dégressif par quantité (ou toute
 * autre règle Lunar) qui serait plus bas est préservé. Les promotions (discounts)
 * s'appliquent après, dans le pipeline panier → elles peuvent descendre encore.
 * Objectif : toujours le prix le plus avantageux pour le client.
 *
 * En admin, Auth::user() est un membre du staff (pas un utilisateur Lunar) → le
 * PricingManager laisse $user à null → ce pipeline ne fait rien. L'impersonation
 * client (à venir) posera un utilisateur Lunar et réactivera l'application.
 */
class NegotiatedPricePipeline
{
    public function handle(PricingManagerInterface $manager, Closure $next)
    {
        $variant = $manager->purchasable;
        $user = $manager->user;

        if ($user === null || ! $variant instanceof ProductVariant) {
            return $next($manager);
        }

        $customerIds = $user->customers->pluck('id');

        if ($customerIds->isEmpty()) {
            return $next($manager);
        }

        $negotiated = NegotiatedPrice::query()
            ->whereIn('customer_id', $customerIds)
            ->where('product_variant_id', $variant->id)
            ->where('currency_id', $manager->currency->id)
            ->min('price');

        if ($negotiated !== null && (int) $negotiated < $manager->pricing->matched->price->value) {
            $matched = clone $manager->pricing->matched;
            $matched->price = new Price((int) $negotiated, $manager->currency, 1);
            $manager->pricing->matched = $matched;
        }

        return $next($manager);
    }
}

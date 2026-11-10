<?php

declare(strict_types=1);

namespace Pko\Loyalty\Services;

use Lunar\Models\Contracts\Cart;
use Lunar\Models\Customer;
use Pko\Loyalty\Enums\GiftType;
use Pko\Loyalty\Models\GiftHistory;
use Pko\ShippingCommon\Contracts\CustomerFrancoThresholdProvider;

/**
 * Avantage fidélité « franco abaissé » : seuil de franco personnel d'un client.
 *
 * Source : les déblocages (GiftHistory) de l'année en cours d'un palier de type
 * franco. Un déblocage vaut jusqu'au 31/12 (la ligne porte son année et les
 * points repartent à zéro au 1er janvier) ; un remboursement qui fait perdre
 * le palier supprime la ligne (`LoyaltyManager::revokeForRefunds()`). Plusieurs
 * paliers franco débloqués → le seuil le plus bas.
 *
 * Lié à `CustomerFrancoThresholdProvider` par LoyaltyServiceProvider, seulement
 * si pko/shipping-common est installé.
 */
class LoyaltyFrancoThreshold implements CustomerFrancoThresholdProvider
{
    public function __construct(
        private readonly LoyaltyManager $manager,
    ) {}

    public function thresholdCentsFor(Cart $cart): ?int
    {
        return $this->forCustomers($this->customerIdsFor($cart));
    }

    /**
     * Seuil personnel en cents HT d'un client, ou null s'il n'en a pas.
     */
    public function forCustomer(int $customerId): ?int
    {
        return $this->forCustomers([$customerId]);
    }

    /**
     * @param  list<int>  $customerIds
     */
    private function forCustomers(array $customerIds): ?int
    {
        if ($customerIds === []) {
            return null;
        }

        $min = GiftHistory::query()
            ->join('pko_loyalty_tiers', 'pko_loyalty_tiers.id', '=', 'pko_loyalty_gift_history.tier_id')
            ->whereIn('pko_loyalty_gift_history.customer_id', $customerIds)
            ->where('pko_loyalty_gift_history.year', $this->manager->currentYear())
            ->where('pko_loyalty_tiers.gift_type', GiftType::Franco->value)
            ->whereNotNull('pko_loyalty_tiers.franco_threshold')
            ->min('pko_loyalty_tiers.franco_threshold');

        return $min !== null ? (int) $min : null;
    }

    /**
     * Client du panier : `customer_id` s'il est posé, sinon les clients rattachés
     * à l'utilisateur du panier. `isset()` plutôt qu'un accès direct : sans
     * attribut posé, il ne déclenche ni requête ni accès à une relation.
     *
     * @return list<int>
     */
    private function customerIdsFor(Cart $cart): array
    {
        if (isset($cart->customer_id)) {
            return [(int) $cart->customer_id];
        }

        if (! isset($cart->user_id)) {
            return [];
        }

        return Customer::query()
            ->whereHas('users', fn ($q) => $q->whereKey($cart->user_id))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}

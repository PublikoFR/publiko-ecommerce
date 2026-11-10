<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Pricing;

use Lunar\DataTypes\Price;
use Lunar\Models\Contracts\Currency;

/**
 * Avantage retenu sur un panier par CartDiscountArbiter (montants HT en cents).
 *
 * - `winner` : `customer_discount` (remise client % gardée, promotions écartées) ou
 *   `promotion` (promotions/coupons Lunar appliqués sur le prix non remisé).
 * - `customerDiscountCents` : économie apportée par la remise client %.
 * - `promotionCents` : économie apportée par les promotions/coupons (sur prix non
 *   remisé), qu'elles aient gagné ou non ; 0 si aucune promotion ne s'applique.
 * - `outrankedCoupon` : code promo saisi mais non appliqué car la remise client gagne.
 * - `stackedCents` : bons cumulables (bon d'achat fidélité), déduits après.
 */
final class CartAdvantage
{
    public const WINNER_CUSTOMER_DISCOUNT = 'customer_discount';

    public const WINNER_PROMOTION = 'promotion';

    public function __construct(
        public readonly string $winner,
        public readonly int $customerDiscountCents,
        public readonly int $promotionCents,
        public readonly Currency $currency,
        public readonly ?string $outrankedCoupon = null,
        public readonly int $stackedCents = 0,
    ) {}

    public function customerDiscountWins(): bool
    {
        return $this->winner === self::WINNER_CUSTOMER_DISCOUNT;
    }

    public function promotionWins(): bool
    {
        return $this->winner === self::WINNER_PROMOTION;
    }

    /** Une promotion était en concurrence avec la remise client. */
    public function hadCompetition(): bool
    {
        return $this->promotionCents > 0;
    }

    public function headline(): string
    {
        return match (true) {
            $this->promotionWins() => 'Promotion retenue',
            $this->hadCompetition() => 'Votre remise client est retenue',
            default => 'Remise client incluse',
        };
    }

    /** @return list<string> */
    public function details(): array
    {
        $customer = $this->format($this->customerDiscountCents);
        $promotion = $this->format($this->promotionCents);

        $lines = match (true) {
            $this->promotionWins() => [
                "Elle vous fait économiser {$promotion} HT, plus que votre remise client ({$customer} HT). "
                .'Les deux ne se cumulent pas : vos articles sont au prix sans remise client.',
            ],
            $this->hadCompetition() => [
                "Elle vous fait économiser {$customer} HT, plus que la promotion disponible ({$promotion} HT). "
                .'Les deux ne se cumulent pas.',
            ],
            default => ["{$customer} HT d'économie déjà déduits du prix de vos articles."],
        };

        if ($this->outrankedCoupon !== null && $this->customerDiscountWins()) {
            $lines[] = "Le code {$this->outrankedCoupon} n'est donc pas appliqué.";
        }

        if ($this->stackedCents > 0) {
            $lines[] = "Bon d'achat fidélité cumulé : -{$this->format($this->stackedCents)} HT.";
        }

        return $lines;
    }

    /**
     * Forme sérialisable (propriété Livewire, partial `customer-auth::partials.cart-advantage`).
     *
     * @return array{winner: string, headline: string, details: list<string>}
     */
    public function toDisplay(): array
    {
        return ['winner' => $this->winner, 'headline' => $this->headline(), 'details' => $this->details()];
    }

    private function format(int $cents): string
    {
        return (new Price($cents, $this->currency, 1))->formatted();
    }
}

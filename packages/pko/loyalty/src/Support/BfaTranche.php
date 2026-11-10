<?php

declare(strict_types=1);

namespace Pko\Loyalty\Support;

use Illuminate\Support\Carbon;

/**
 * Tranche de BFA : ouverte par le déblocage d'un palier BFA, close au
 * déblocage du palier BFA suivant ou au 31/12. Montants HT en cents.
 */
final class BfaTranche
{
    public function __construct(
        public readonly int $giftHistoryId,
        public readonly int $customerId,
        public readonly ?int $tierId,
        public readonly ?string $tierName,
        public readonly int $year,
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly int $revenueHt,
        public readonly int $fixedAmount,
        public readonly string $percent,
        public readonly int $amount,
    ) {}

    /** La tranche court encore (fin dans le futur). */
    public function isOpen(): bool
    {
        return $this->end->isFuture();
    }
}

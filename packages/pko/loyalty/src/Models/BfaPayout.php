<?php

declare(strict_types=1);

namespace Pko\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Customer;

/**
 * Versement d'une tranche de BFA, émis en avoir comptable autonome.
 *
 * Montants HT en cents. `adjusted_amount` (saisi par l'admin) prime sur
 * `computed_amount` (pré-calcul) : cf. `amountToIssue()`.
 *
 * @property int $id
 * @property int|null $gift_history_id
 * @property int $customer_id
 * @property int $year
 * @property int $revenue_ht
 * @property int $computed_amount
 * @property int|null $adjusted_amount
 * @property string $status
 * @property string $external_reference
 */
class BfaPayout extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUING = 'issuing';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_FAILED = 'failed';

    /** Une émission « en cours » plus vieille que ça est considérée plantée. */
    public const ISSUING_STALE_MINUTES = 10;

    protected $table = 'pko_loyalty_bfa_payouts';

    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'revenue_ht' => 'integer',
        'fixed_amount' => 'integer',
        'percent' => 'decimal:2',
        'computed_amount' => 'integer',
        'adjusted_amount' => 'integer',
        'pennylane_id' => 'integer',
        'issued_at' => 'datetime',
    ];

    public static function externalReferenceFor(int $year, int $giftHistoryId): string
    {
        return 'bfa_'.$year.'_'.$giftHistoryId;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function giftHistory(): BelongsTo
    {
        return $this->belongsTo(GiftHistory::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'tier_id');
    }

    public function amountToIssue(): int
    {
        return $this->adjusted_amount ?? (int) $this->computed_amount;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    /** Montant et tranche encore modifiables (ni émis, ni en cours d'émission). */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_FAILED], true);
    }

    /** Intitulé de la ligne d'avoir. */
    public function label(): string
    {
        return sprintf(
            'Bonus de fin d\'année %d%s — du %s au %s',
            $this->year,
            $this->tier_name ? ' ('.$this->tier_name.')' : '',
            $this->period_start->format('d/m/Y'),
            $this->period_end->format('d/m/Y'),
        );
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'À émettre',
            self::STATUS_ISSUING => 'Émission en cours',
            self::STATUS_ISSUED => 'Avoir émis',
            self::STATUS_FAILED => 'Échec',
            default => $status,
        };
    }
}

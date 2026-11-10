<?php

declare(strict_types=1);

namespace Pko\Loyalty\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Pko\Loyalty\Contracts\BfaCreditNoteIssuer;
use Pko\Loyalty\Models\BfaPayout;

/**
 * Émission des avoirs de BFA, ligne par ligne.
 *
 * Idempotence à deux niveaux : la ligne est « réservée » par une mise à jour
 * conditionnelle (statut → `issuing`) — deux clics simultanés n'émettent
 * qu'une fois — et l'émetteur retrouve un avoir déjà créé par sa référence
 * externe (reprise après un plantage entre la création distante et l'écriture
 * locale). Une réservation plus vieille que `ISSUING_STALE_MINUTES` est
 * considérée plantée et peut être reprise.
 */
class BfaPayoutEmitter
{
    public const RESULT_ISSUED = 'issued';

    public const RESULT_SKIPPED = 'skipped';

    public const RESULT_FAILED = 'failed';

    public function __construct(private readonly BfaCreditNoteIssuer $issuer) {}

    /** Une année n'est émissible qu'une fois close (tranches arrêtées au 31/12). */
    public static function canIssueYear(int $year): bool
    {
        return $year < (int) now()->year;
    }

    public function emit(BfaPayout $payout): string
    {
        if (! self::canIssueYear((int) $payout->year) || $payout->amountToIssue() <= 0) {
            return self::RESULT_SKIPPED;
        }

        if (! $this->claim($payout)) {
            return self::RESULT_SKIPPED;
        }

        $payout->refresh();

        try {
            $remote = $this->issuer->issue($payout);
        } catch (\Throwable $e) {
            Log::error('Émission de l\'avoir BFA impossible', ['payout_id' => $payout->id, 'error' => $e->getMessage()]);

            $payout->update([
                'status' => BfaPayout::STATUS_FAILED,
                'last_error' => mb_substr($e->getMessage(), 0, 5000),
            ]);

            return self::RESULT_FAILED;
        }

        $payout->update([
            'status' => BfaPayout::STATUS_ISSUED,
            'pennylane_id' => $remote['id'],
            'credit_note_number' => $remote['number'],
            'last_error' => null,
            'issued_at' => Carbon::now(),
        ]);

        return self::RESULT_ISSUED;
    }

    /**
     * @return array<string, int> nombre de lignes par résultat
     */
    public function emitYear(int $year): array
    {
        $counts = [self::RESULT_ISSUED => 0, self::RESULT_SKIPPED => 0, self::RESULT_FAILED => 0];

        BfaPayout::query()
            ->where('year', $year)
            ->whereIn('status', [BfaPayout::STATUS_DRAFT, BfaPayout::STATUS_FAILED, BfaPayout::STATUS_ISSUING])
            ->orderBy('id')
            ->each(function (BfaPayout $payout) use (&$counts): void {
                $counts[$this->emit($payout)]++;
            });

        return $counts;
    }

    private function claim(BfaPayout $payout): bool
    {
        $stale = Carbon::now()->subMinutes(BfaPayout::ISSUING_STALE_MINUTES);

        return BfaPayout::query()
            ->whereKey($payout->id)
            ->where(fn ($q) => $q
                ->whereIn('status', [BfaPayout::STATUS_DRAFT, BfaPayout::STATUS_FAILED])
                ->orWhere(fn ($q) => $q->where('status', BfaPayout::STATUS_ISSUING)->where('updated_at', '<', $stale)))
            ->update(['status' => BfaPayout::STATUS_ISSUING, 'updated_at' => Carbon::now()]) === 1;
    }
}

<?php

declare(strict_types=1);

namespace Pko\Loyalty\Support;

/**
 * Affichage d'un montant en cents (« 50 € », « 75,50 € »). Arithmétique
 * entière : aucun float ne transite par un montant.
 */
final class Money
{
    public static function format(int $cents): string
    {
        $euros = number_format(intdiv($cents, 100), 0, ',', ' ');
        $rest = abs($cents % 100);

        return $rest === 0
            ? $euros.' €'
            : $euros.','.str_pad((string) $rest, 2, '0', STR_PAD_LEFT).' €';
    }
}

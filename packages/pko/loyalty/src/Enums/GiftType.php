<?php

declare(strict_types=1);

namespace Pko\Loyalty\Enums;

/**
 * Nature de l'avantage offert par un palier de fidélité.
 *
 * - `Voucher` : bon d'achat Lunar à usage unique émis au déblocage.
 * - `Franco` : seuil de franco de port abaissé pour le client.
 * - `Bfa` : bonus de fin d'année (montant fixe + pourcentage du CA de la
 *   tranche), versé en avoir comptable en janvier (`BfaCalculator`).
 *
 * Le bon d'achat est émis par ce package. Le franco abaissé est exposé au
 * calcul des frais de port par `LoyaltyFrancoThreshold` (contrat de
 * pko/shipping-common). Le BFA est calculé ici et versé en avoir via le contrat
 * `BfaCreditNoteIssuer` ; son effet comptable vit ailleurs
 * (cf. docs/packages/loyalty.md).
 */
enum GiftType: string
{
    case Voucher = 'voucher';
    case Franco = 'franco';
    case Bfa = 'bfa';

    public function label(): string
    {
        return match ($this) {
            self::Voucher => "Bon d'achat",
            self::Franco => 'Franco de port abaissé',
            self::Bfa => 'BFA',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}

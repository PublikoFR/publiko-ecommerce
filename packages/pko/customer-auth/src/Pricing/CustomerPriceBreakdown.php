<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Pricing;

/**
 * Détail du prix HT client d'une variante (en centimes), calculé par
 * CustomerDiscountResolver et appliqué par NegotiatedPricePipeline.
 *
 * - `originalCents` : prix Lunar déjà résolu (base / groupe client / dégressif)
 *   AVANT prix négocié et remise client → prix barré à l'affichage.
 * - `finalCents`    : min(prix résolu, prix négocié, prix remisé).
 * - `source`        : ce qui a fixé `finalCents`.
 * - `discountBasisPoints` : plus forte remise couvrant le produit (1250 = 12,5 %),
 *   null si aucune (ou produit non remisable) — même si elle n'a pas gagné.
 */
final class CustomerPriceBreakdown
{
    public const SOURCE_CATALOG = 'catalog';

    public const SOURCE_NEGOTIATED = 'negotiated';

    public const SOURCE_DISCOUNT = 'discount';

    public function __construct(
        public readonly int $originalCents,
        public readonly int $finalCents,
        public readonly string $source = self::SOURCE_CATALOG,
        public readonly ?int $negotiatedCents = null,
        public readonly ?int $discountBasisPoints = null,
        public readonly ?int $discountedCents = null,
    ) {}

    public static function catalog(int $cents): self
    {
        return new self($cents, $cents);
    }

    /** La remise client en % est ce qui fixe le prix final (prix barré + badge). */
    public function isDiscounted(): bool
    {
        return $this->source === self::SOURCE_DISCOUNT;
    }

    /** % effectivement appliqué au prix final (null si la remise n'a pas gagné). */
    public function appliedDiscountPercent(): ?float
    {
        return $this->isDiscounted() ? $this->discountBasisPoints / 100 : null;
    }

    /** Plus forte remise % couvrant le produit, appliquée ou non. */
    public function discountPercent(): ?float
    {
        return $this->discountBasisPoints !== null ? $this->discountBasisPoints / 100 : null;
    }

    /** Prix final inférieur au prix catalogue résolu (négocié ou remisé). */
    public function isBelowCatalog(): bool
    {
        return $this->finalCents < $this->originalCents;
    }
}

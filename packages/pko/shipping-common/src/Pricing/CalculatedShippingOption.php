<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pricing;

/**
 * Option de livraison calculée, avec ventilation des coûts.
 *
 * Produit par ShippingCalculator::calculate() avant conversion en
 * Lunar\DataTypes\ShippingOption par UnifiedShippingModifier.
 */
final class CalculatedShippingOption
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $carrierCode,
        public readonly string $serviceCode,
        public readonly string $name,
        public readonly string $description,
        /** Prix de grille HT (cents) — 0 si franco appliqué ou cas flat-only. */
        public int $gridPriceCents,
        /** Somme des forfaits transport des lignes "flat" (cents) × quantité. */
        public readonly int $flatPriceCents,
        /** Supplément(s) auto cumulés (cents). */
        public int $autoSurchargeCents,
        /** Le service est offert (franco). */
        public bool $franco,
        /** Option sentinel "sur devis" (meta.quote=true), price toujours 0. */
        public readonly bool $isSentinel,
        /** Le service nécessite le choix d'un point relais. */
        public readonly bool $requiresPickupPoint,
    ) {}

    /** Prix total HT (cents) affiché au client. */
    public function totalPriceCents(): int
    {
        return $this->gridPriceCents + $this->flatPriceCents + $this->autoSurchargeCents;
    }
}

<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pricing;

/**
 * Résultat d'un calcul de frais de port pour un panier.
 *
 * Objet valeur immutable produit par ShippingCalculator::calculate().
 * Consommé par UnifiedShippingModifier pour peupler le ShippingManifest Lunar.
 *
 * Banners :
 *   ['type' => 'franco_reached']
 *   ['type' => 'franco_progress', 'remaining_cents' => int]
 *   ['type' => 'excluded_lines']  — lignes flat facturées en sus
 *   ['type' => 'multi_colis']     — origines d'expédition multiples
 */
final class ShippingQuote
{
    /**
     * @param  CalculatedShippingOption[]  $options
     * @param  list<array<string, mixed>>  $banners
     * @param  list<string>  $blockers
     */
    public function __construct(
        public readonly array $options,
        public readonly string $defaultOptionIdentifier,
        public readonly array $banners,
        public readonly array $blockers,
    ) {}

    public function isEmpty(): bool
    {
        return $this->options === [];
    }

    /** True si au moins une option a le franco appliqué. */
    public function hasFrancoOption(): bool
    {
        foreach ($this->options as $opt) {
            if ($opt->franco) {
                return true;
            }
        }

        return false;
    }
}

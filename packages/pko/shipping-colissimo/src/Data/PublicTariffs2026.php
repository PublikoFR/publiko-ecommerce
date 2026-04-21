<?php

declare(strict_types=1);

namespace Pko\ShippingColissimo\Data;

/**
 * Tarifs publics Colissimo France métropolitaine — baseline 2026.
 *
 * Source : grille publique La Poste / Colissimo publiée pour les clients sans
 * contrat B2B. Valeurs à mettre à jour annuellement lorsque La Poste publie une
 * nouvelle grille (créer un fichier PublicTariffs2027.php et bumper la classe
 * référencée dans ColissimoConfig::presetClass()).
 *
 * NB : pour les clients avec contrat négocié, saisir la grille à la main dans
 * la page Config (ces valeurs publiques sont des planchers ; les contrats sont
 * généralement moins chers).
 */
final class PublicTariffs2026
{
    public const YEAR = 2026;

    /**
     * @var array<int, array{service_code: string, label: string, enabled: bool, sort: int}>
     */
    public const SERVICES = [
        ['service_code' => 'DOM', 'label' => 'Colissimo Domicile sans signature', 'enabled' => true, 'sort' => 10],
        ['service_code' => 'DOS', 'label' => 'Colissimo Domicile avec signature', 'enabled' => true, 'sort' => 20],
    ];

    /**
     * Paliers de poids → prix TTC en cents.
     * Valeurs indicatives 2026 à vérifier sur laposte.fr avant mise en prod.
     *
     * @var array<int, array{service_code: string|null, max_kg: int, price_cents: int, sort: int}>
     */
    public const GRID = [
        ['service_code' => null, 'max_kg' => 1, 'price_cents' => 825, 'sort' => 10],
        ['service_code' => null, 'max_kg' => 2, 'price_cents' => 995, 'sort' => 20],
        ['service_code' => null, 'max_kg' => 5, 'price_cents' => 1490, 'sort' => 30],
        ['service_code' => null, 'max_kg' => 10, 'price_cents' => 1790, 'sort' => 40],
        ['service_code' => null, 'max_kg' => 30, 'price_cents' => 2690, 'sort' => 50],
    ];
}

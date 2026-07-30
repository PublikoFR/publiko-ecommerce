<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Pko\ShippingCommon\Models\Supplier;

/**
 * Résout le mode de port effectif d'un produit.
 *
 * Le mode 'inherit' délègue la décision au champ port_inclus du fournisseur.
 * Tous les autres modes sont retournés tels quels.
 *
 * Résolution de l'héritage :
 *   - port_inclus = 'oui'        → 'free'
 *   - port_inclus = 'non'        → 'standard'
 *   - port_inclus = 'cas_par_cas'→ 'standard' (prudent — en attente de décision)
 *   - pas de fournisseur         → 'standard'
 */
final class PortModeResolver
{
    /** @var array<int, string> */
    private static array $supplierCache = [];

    /**
     * @param  object  $product  Doit exposer pko_port_mode (string) et pko_supplier_id (?int).
     */
    public static function resolve(object $product): string
    {
        $mode = (string) ($product->pko_port_mode ?? 'standard');

        if ($mode !== 'inherit') {
            return $mode;
        }

        $supplierId = isset($product->pko_supplier_id) ? (int) $product->pko_supplier_id : null;

        if ($supplierId === null || $supplierId === 0) {
            return 'standard';
        }

        if (! array_key_exists($supplierId, self::$supplierCache)) {
            self::$supplierCache[$supplierId] = Supplier::find($supplierId)?->port_inclus ?? 'cas_par_cas';
        }

        return match (self::$supplierCache[$supplierId]) {
            'oui' => 'free',
            default => 'standard',
        };
    }

    /**
     * Vide le cache statique — à appeler en setUp() des tests pour éviter la pollution inter-tests.
     */
    public static function flushCache(): void
    {
        self::$supplierCache = [];
    }

    /**
     * Dérive l'éligibilité franco par défaut à partir du mode résolu.
     * Seul le mode 'standard' est éligible.
     */
    public static function deriveFrancoEligible(string $resolvedMode): bool
    {
        return $resolvedMode === 'standard';
    }
}

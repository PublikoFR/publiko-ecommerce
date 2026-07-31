<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Pko\ShippingCommon\Models\CarrierService;
use Throwable;

/**
 * Traduit un code de service interne (chrono13, chrono_relais…) en code produit
 * attendu par le web service du transporteur (1, 2, 86…).
 *
 * Ordre de résolution :
 *   1. colonne `carrier_product_code` de `pko_carrier_services` (source d'autorité,
 *      éditable depuis Back-office → Expédition → Transporteurs) ;
 *   2. clé `product_codes.<service>` du fichier de config du transporteur (secours
 *      pour les tests unitaires et une install sans data) ;
 *   3. le code de service lui-même — correct pour Colissimo dont les codes internes
 *      (DOM / DOS) SONT déjà les codes produits.
 */
final class CarrierProductCodeResolver
{
    /** @var array<string, string> */
    private array $cache = [];

    public function resolve(string $carrierCode, string $serviceCode): string
    {
        $key = $carrierCode.'/'.$serviceCode;

        return $this->cache[$key] ??= $this->lookup($carrierCode, $serviceCode);
    }

    private function lookup(string $carrierCode, string $serviceCode): string
    {
        try {
            $fromDb = CarrierService::query()
                ->where('carrier_code', $carrierCode)
                ->where('service_code', $serviceCode)
                ->value('carrier_product_code');

            if (is_string($fromDb) && $fromDb !== '') {
                return $fromDb;
            }
        } catch (Throwable) {
            // Table absente (tests unitaires sans base) → on tombe sur la config.
        }

        $fromConfig = config("{$carrierCode}.product_codes.{$serviceCode}");

        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

        return $serviceCode;
    }
}

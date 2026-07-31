<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Brand;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Pko\ShippingCommon\Models\Supplier;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_required_baseline(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 50 produits de démo + 20 produits de test expédition (SKU TX-*, cf. shipping.md §5.17)
        $this->assertSame(70, Product::query()->count());
        $this->assertSame(20, ProductVariant::query()->where('sku', 'like', 'TX-%')->count());
        // 3 fournisseurs : un par valeur de port_inclus (non / oui / cas_par_cas)
        $this->assertSame(3, Supplier::query()->count());
        $this->assertGreaterThanOrEqual(3, LunarCollection::query()->count());
        // nouveau-client (défaut) + particuliers + 4 groupes « métier »
        // (installateurs, plombiers, electriciens, revendeurs) — cf. PkoCustomerGroupSeeder.
        $this->assertSame(6, CustomerGroup::query()->count());
        $this->assertSame(10, Order::query()->count());
        $this->assertGreaterThanOrEqual(5, Brand::query()->count());
    }

    /**
     * Garde anti-régression : plus aucune méthode table-rate ne doit être seedée.
     *
     * `PkoShippingSeeder` en créait 3 (pko-standard / pko-pickup / pko-free) et
     * les schedulait sur tous les groupes clients — elles remontaient donc au
     * checkout à côté des services Chronopost, sans UI pour les gérer et en
     * doublon du franco. Le calcul des frais de port passe intégralement par
     * `UnifiedShippingModifier`. Re-seeder une méthode table-rate la ferait
     * réapparaître au tunnel de commande.
     */
    public function test_no_table_rate_shipping_method_is_seeded(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, ShippingMethod::query()->count());
        $this->assertSame(0, ShippingRate::query()->count());
        $this->assertSame(0, ShippingZone::query()->count());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\TaxClass;
use Pko\ShippingCommon\Models\Supplier;
use Pko\ShippingCommon\Support\PortModeResolver;
use Tests\TestCase;

/**
 * Résolution d'héritage : les 3 cas fournisseur × modes produit.
 */
class PortModeResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TaxClass::query()->firstOrCreate(['name' => 'TVA 20%'], ['default' => true]);
        PortModeResolver::flushCache();
    }

    // ── Modes explicites (pas d'héritage) ────────────────────────────────────

    public function test_standard_retourne_standard(): void
    {
        $product = (object) ['pko_port_mode' => 'standard', 'pko_supplier_id' => null];
        $this->assertSame('standard', PortModeResolver::resolve($product));
    }

    public function test_free_retourne_free(): void
    {
        $product = (object) ['pko_port_mode' => 'free', 'pko_supplier_id' => null];
        $this->assertSame('free', PortModeResolver::resolve($product));
    }

    public function test_flat_retourne_flat(): void
    {
        $product = (object) ['pko_port_mode' => 'flat', 'pko_supplier_id' => null];
        $this->assertSame('flat', PortModeResolver::resolve($product));
    }

    public function test_quote_retourne_quote(): void
    {
        $product = (object) ['pko_port_mode' => 'quote', 'pko_supplier_id' => null];
        $this->assertSame('quote', PortModeResolver::resolve($product));
    }

    // ── Héritage sans fournisseur ────────────────────────────────────────────

    public function test_inherit_sans_fournisseur_retourne_standard(): void
    {
        $product = (object) ['pko_port_mode' => 'inherit', 'pko_supplier_id' => null];
        $this->assertSame('standard', PortModeResolver::resolve($product));
    }

    // ── Héritage avec fournisseur (les 3 cas port_inclus) ───────────────────

    public function test_inherit_avec_fournisseur_port_inclus_oui_retourne_free(): void
    {
        $supplier = Supplier::create(['name' => 'Fournisseur OUI', 'port_inclus' => 'oui']);
        $product = (object) ['pko_port_mode' => 'inherit', 'pko_supplier_id' => $supplier->id];

        $this->assertSame('free', PortModeResolver::resolve($product));
    }

    public function test_inherit_avec_fournisseur_port_inclus_non_retourne_standard(): void
    {
        $supplier = Supplier::create(['name' => 'Fournisseur NON', 'port_inclus' => 'non']);
        $product = (object) ['pko_port_mode' => 'inherit', 'pko_supplier_id' => $supplier->id];

        $this->assertSame('standard', PortModeResolver::resolve($product));
    }

    public function test_inherit_avec_fournisseur_port_inclus_cas_par_cas_retourne_standard(): void
    {
        $supplier = Supplier::create(['name' => 'Fournisseur CAS', 'port_inclus' => 'cas_par_cas']);
        $product = (object) ['pko_port_mode' => 'inherit', 'pko_supplier_id' => $supplier->id];

        // Cas par cas → prudent = standard (en attente de décision)
        $this->assertSame('standard', PortModeResolver::resolve($product));
    }

    // ── deriveFrancoEligible ─────────────────────────────────────────────────

    public function test_derive_franco_eligible_vrai_uniquement_pour_standard(): void
    {
        $this->assertTrue(PortModeResolver::deriveFrancoEligible('standard'));
        $this->assertFalse(PortModeResolver::deriveFrancoEligible('free'));
        $this->assertFalse(PortModeResolver::deriveFrancoEligible('flat'));
        $this->assertFalse(PortModeResolver::deriveFrancoEligible('quote'));
        $this->assertFalse(PortModeResolver::deriveFrancoEligible('inherit'));
    }
}

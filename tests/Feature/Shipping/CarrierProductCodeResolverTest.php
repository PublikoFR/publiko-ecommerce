<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\ShippingCommon\Models\CarrierService;
use Pko\ShippingCommon\Support\CarrierProductCodeResolver;
use Tests\TestCase;

/**
 * Le code de service interne (chrono13, chrono_relais…) n'est PAS le code produit
 * attendu par le WS transporteur. Envoyé tel quel, il faisait échouer toute création
 * d'étiquette Chronopost.
 *
 * Les services Chronopost sont déjà semés par les data-migrations du package : les
 * tests écrivent donc par updateOrCreate plutôt que create.
 */
class CarrierProductCodeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_migrations_renseignent_les_codes_produits_chronopost(): void
    {
        $resolver = new CarrierProductCodeResolver;

        // Deux caractères exigés par le WS (doc VL3.25.10.10) : « 1 » → erreur 33.
        $this->assertSame('01', $resolver->resolve('chronopost', 'chrono13'));
        $this->assertSame('02', $resolver->resolve('chronopost', 'chrono10'));
        $this->assertSame('86', $resolver->resolve('chronopost', 'chrono_relais'));
    }

    public function test_les_services_hors_contrat_sont_desactives(): void
    {
        $this->assertFalse((bool) CarrierService::query()
            ->where('carrier_code', 'chronopost')
            ->where('service_code', 'chrono10')
            ->value('enabled'));
        $this->assertTrue((bool) CarrierService::query()
            ->where('carrier_code', 'chronopost')
            ->where('service_code', 'chrono13')
            ->value('enabled'));
    }

    public function test_la_base_prime_sur_la_config(): void
    {
        config(['chronopost.product_codes.chrono13' => '99']);

        $this->setProductCode('chrono13', '1');

        $this->assertSame('1', (new CarrierProductCodeResolver)->resolve('chronopost', 'chrono13'));
    }

    public function test_repli_sur_la_config_quand_la_colonne_est_vide(): void
    {
        config(['chronopost.product_codes.chrono10' => '02']);

        $this->setProductCode('chrono10', null);

        $this->assertSame('02', (new CarrierProductCodeResolver)->resolve('chronopost', 'chrono10'));
    }

    public function test_repli_sur_le_code_de_service_lui_meme(): void
    {
        // Cas Colissimo : DOM / DOS SONT déjà les codes produits.
        $this->assertSame('DOM', (new CarrierProductCodeResolver)->resolve('colissimo', 'DOM'));
    }

    public function test_un_service_inconnu_retombe_sur_son_propre_code(): void
    {
        $this->assertSame('inexistant', (new CarrierProductCodeResolver)->resolve('chronopost', 'inexistant'));
    }

    public function test_le_resultat_est_memoise_par_instance(): void
    {
        $this->setProductCode('chrono13', '1');

        $resolver = new CarrierProductCodeResolver;
        $this->assertSame('1', $resolver->resolve('chronopost', 'chrono13'));

        $this->setProductCode('chrono13', '2');

        $this->assertSame('1', $resolver->resolve('chronopost', 'chrono13'), 'La résolution doit être mémoïsée sur la durée du job');
    }

    private function setProductCode(string $serviceCode, ?string $productCode): void
    {
        CarrierService::query()->updateOrCreate(
            ['carrier_code' => 'chronopost', 'service_code' => $serviceCode],
            ['carrier_product_code' => $productCode, 'label' => $serviceCode, 'enabled' => true],
        );
    }
}

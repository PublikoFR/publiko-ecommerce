<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Livewire\Components\ShippingOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Lunar\Base\ShippingManifestInterface;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingCommon\Contracts\PickupPointProvider;
use Pko\ShippingCommon\Dto\PickupPoint;
use Pko\ShippingCommon\Modifiers\FrancoModifier;
use Pko\ShippingCommon\Support\WeightCalculator;
use Tests\TestCase;

class ShippingOptionsTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeOption(string $identifier, int $priceCents, bool $franco = false): ShippingOption
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $taxClass = TaxClass::make(['name' => 'Default', 'default' => true]);

        return new ShippingOption(
            name: $identifier,
            description: '',
            identifier: $identifier,
            price: new Price($priceCents, $currency, 1),
            taxClass: $taxClass,
            meta: $franco ? ['franco' => true] : [],
        );
    }

    private function bindManifestWith(array $options): void
    {
        $manifest = Mockery::mock(ShippingManifestInterface::class);
        $manifest->shouldReceive('getOptions')
            ->andReturn(new Collection($options));
        // Le recalcul du panier (CartSession::current() via les computed properties
        // du composant) interroge l'option d'expédition choisie sur le manifest.
        $manifest->shouldReceive('getShippingOption')
            ->andReturnNull();

        $this->app->instance(ShippingManifestInterface::class, $manifest);
    }

    private function makeCart(): Cart
    {
        $currency = Currency::factory()->create(['default' => true]);
        $cart = Cart::factory()->create(['currency_id' => $currency->id]);
        CartSession::use($cart);

        return $cart;
    }

    private function makeCartWithAddress(string $postcode = '75001'): Cart
    {
        $currency = Currency::factory()->create(['default' => true]);
        $country = Country::factory()->create(['iso2' => 'FR', 'iso3' => 'FRA']);
        $cart = Cart::factory()->create(['currency_id' => $currency->id]);

        $cart->shippingAddress()->create([
            'type' => 'shipping',
            'first_name' => 'Test',
            'last_name' => 'Relais',
            'line_one' => '1 rue de Test',
            'city' => 'Paris',
            'postcode' => $postcode,
            'country_id' => $country->id,
        ]);

        CartSession::use($cart);

        return $cart;
    }

    /**
     * Lie un provider de points relais factice retournant un point connu.
     */
    private function bindPickupProviderWith(array $points): void
    {
        $this->app->bind(PickupPointProvider::class, fn () => new class($points) implements PickupPointProvider
        {
            public function __construct(private array $points) {}

            public function search(string $postcode, string $countryCode = 'FR', ?string $serviceCode = null): array
            {
                return $this->points;
            }
        });
    }

    private function makeLine(bool $francoEligible, int $subtotalHtCents = 20000, ?int $supplierId = null): object
    {
        $product = (object) [
            'pko_franco_eligible' => $francoEligible,
            'pko_port_mode' => 'standard',
            'pko_supplier_id' => $supplierId,
        ];

        $variant = (object) [
            'weight_value' => 1.0,
            'weight_unit' => 'kg',
            'product' => $product,
        ];

        return (object) [
            'purchasable' => $variant,
            'quantity' => 1,
            'subTotal' => (object) ['value' => $subtotalHtCents],
        ];
    }

    private function mockCart(array $lines): Cart&MockInterface
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $cart = Mockery::mock(Cart::class);
        $cart->shouldReceive('getAttribute')->with('lines')->andReturn(new Collection($lines));
        $cart->shouldReceive('getAttribute')->with('currency')->andReturn($currency);

        return $cart;
    }

    // ── Tests mount() ─────────────────────────────────────────────────────────

    public function test_chrono13_selectionne_par_defaut_quand_pas_doption_sauvegardee(): void
    {
        $this->makeCart();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
            $this->makeOption('chronopost.chrono13', 1890),
            $this->makeOption('chronopost.chrono10', 2490),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSet('chosenOption', FrancoModifier::CHRONO13_IDENTIFIER);
    }

    public function test_premiere_option_si_chrono13_absent(): void
    {
        $this->makeCart();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
            $this->makeOption('chronopost.chrono10', 2490),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSet('chosenOption', 'chronopost.chrono_relais');
    }

    // ── Tests bandeaux via WeightCalculator ──────────────────────────────────

    public function test_is_franco_reached_vrai_quand_seuil_atteint_sans_exclusion(): void
    {
        $cart = $this->mockCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
        ]);

        $this->assertTrue(
            WeightCalculator::francoEligibleSubtotalHt($cart) >= 35000
            && ! WeightCalculator::cartHasFrancoExcludedLine($cart),
            'Franco doit être atteint : 400 € HT de lignes éligibles sans exclusion'
        );
    }

    public function test_is_franco_reached_faux_quand_seuil_non_atteint(): void
    {
        $cart = $this->mockCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 20000),
        ]);

        $this->assertFalse(
            WeightCalculator::francoEligibleSubtotalHt($cart) >= 35000,
            'Franco ne doit pas être atteint avec seulement 200 € HT'
        );
    }

    public function test_has_excluded_lines_vrai_quand_une_ligne_est_exclue(): void
    {
        $cart = $this->mockCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 40000),
            $this->makeLine(francoEligible: false, subtotalHtCents: 5000),
        ]);

        $this->assertTrue(WeightCalculator::cartHasFrancoExcludedLine($cart));
    }

    // ── Affichage HT / TTC ────────────────────────────────────────────────────

    public function test_affiche_prix_ht_et_ttc(): void
    {
        $this->makeCartWithAddress();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono13', 1890),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSee('HT')
            ->assertSee('TTC');
    }

    // ── Sélection point relais (Chrono Relais) ────────────────────────────────

    public function test_point_relais_requis_quand_chrono_relais_choisi(): void
    {
        $cart = $this->makeCartWithAddress();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
            $this->makeOption('chronopost.chrono13', 1890),
        ]);

        Livewire::test(ShippingOptions::class)
            ->set('chosenOption', 'chronopost.chrono_relais')
            ->call('save')
            ->assertHasErrors('pickupPointId');

        $this->assertArrayNotHasKey('pickup_point', $cart->refresh()->meta?->toArray() ?? []);
    }

    public function test_point_relais_selectionne_dans_la_liste_est_persiste_en_meta(): void
    {
        $cart = $this->makeCartWithAddress('75001');

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
        ]);

        $this->bindPickupProviderWith([
            new PickupPoint(
                id: 'PR123',
                name: 'Relais du Centre',
                address1: '2 rue de la Paix',
                postcode: '75001',
                city: 'Paris',
            ),
        ]);

        Livewire::test(ShippingOptions::class)
            ->set('chosenOption', 'chronopost.chrono_relais')
            ->call('searchPickupPoints')
            ->assertCount('pickupPoints', 1)
            ->set('pickupPointId', 'PR123')
            ->call('save')
            ->assertHasNoErrors();

        $point = $cart->refresh()->meta['pickup_point'] ?? null;
        $this->assertIsArray($point);
        $this->assertSame('PR123', $point['id']);
        $this->assertSame('Relais du Centre', $point['name']);
    }

    public function test_saisie_manuelle_du_point_relais_est_persistee(): void
    {
        $cart = $this->makeCartWithAddress();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
        ]);

        Livewire::test(ShippingOptions::class)
            ->set('chosenOption', 'chronopost.chrono_relais')
            ->set('manualPickupPoint.name', 'Point Manuel')
            ->set('manualPickupPoint.address1', '3 rue Z')
            ->set('manualPickupPoint.postcode', '34500')
            ->set('manualPickupPoint.city', 'Béziers')
            ->call('save')
            ->assertHasNoErrors();

        $point = $cart->refresh()->meta['pickup_point'] ?? null;
        $this->assertIsArray($point);
        $this->assertSame('Point Manuel', $point['name']);
        $this->assertSame('34500', $point['postcode']);
    }

    public function test_changer_pour_un_service_sans_relais_purge_le_point_en_meta(): void
    {
        $cart = $this->makeCartWithAddress();
        $cart->meta = ['pickup_point' => ['id' => 'OLD', 'name' => 'Ancien']];
        $cart->save();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
            $this->makeOption('chronopost.chrono13', 1890),
        ]);

        Livewire::test(ShippingOptions::class)
            ->set('chosenOption', 'chronopost.chrono13')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertArrayNotHasKey('pickup_point', $cart->refresh()->meta?->toArray() ?? []);
    }
}

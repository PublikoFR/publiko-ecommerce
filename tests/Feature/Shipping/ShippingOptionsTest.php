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
use Pko\ShippingCommon\Modifiers\UnifiedShippingModifier;
use Pko\ShippingCommon\Pricing\ShippingCalculator;
use Pko\ShippingCommon\Pricing\ShippingQuote;
use Pko\ShippingCommon\Support\WeightCalculator;
use Tests\TestCase;

class ShippingOptionsTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $extraMeta  Méta additionnels (ex. flat_price_cents, surcharge_cents).
     */
    private function makeOption(string $identifier, int $priceCents, bool $franco = false, array $extraMeta = []): ShippingOption
    {
        $currency = Currency::make(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2]);
        $taxClass = TaxClass::make(['name' => 'Default', 'default' => true]);

        $meta = $franco ? ['franco' => true] : [];
        $meta = array_merge($meta, $extraMeta);

        return new ShippingOption(
            name: $identifier,
            description: '',
            identifier: $identifier,
            price: new Price($priceCents, $currency, 1),
            taxClass: $taxClass,
            meta: $meta,
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
     *
     * À binder dans TOUT test qui sélectionne Chrono Relais : le composant lance
     * désormais une recherche automatique au changement d'option, et sans double
     * c'est le provider Chronopost réel (donc un appel SOAP) qui serait résolu.
     *
     * @param  list<PickupPoint>  $points
     * @param  string|null  $error  motif d'échec simulé (service injoignable)
     */
    private function bindPickupProviderWith(array $points, ?string $error = null): void
    {
        $this->app->bind(PickupPointProvider::class, fn () => new class($points, $error) implements PickupPointProvider
        {
            public function __construct(private array $points, private ?string $error) {}

            public function search(string $postcode, string $countryCode = 'FR', ?string $serviceCode = null): array
            {
                return $this->points;
            }

            public function lastSearchError(): ?string
            {
                return $this->error;
            }
        });
    }

    /**
     * Lie un ShippingCalculator factice retournant des bandeaux prédéfinis.
     * Évite tout appel DB au calculator (grilles, services, surcharges) dans les tests
     * qui ne veulent tester que la consommation des banners côté composant.
     *
     * @param  list<array<string, mixed>>  $banners
     */
    private function bindFakeCalculator(array $banners): void
    {
        $fakeQuote = new ShippingQuote(
            options: [],
            defaultOptionIdentifier: ShippingCalculator::DEFAULT_OPTION_IDENTIFIER,
            banners: $banners,
            blockers: [],
        );

        $this->app->bind(ShippingCalculator::class, fn () => new class($fakeQuote)
        {
            public function __construct(private ShippingQuote $quote) {}

            public function calculate(mixed $cart): ShippingQuote
            {
                return $this->quote;
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
            ->assertSet('chosenOption', UnifiedShippingModifier::CHRONO13_IDENTIFIER);
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

    // ── Tests bandeaux — source unique : ShippingQuote::$banners ─────────────

    /**
     * Test clé : les bandeaux affichés viennent du quote (calculator mocké),
     * pas d'un recalcul WeightCalculator dans le composant.
     */
    public function test_banners_proviennent_du_quote_pas_recalcules(): void
    {
        $this->makeCartWithAddress();
        $this->bindManifestWith([$this->makeOption('chronopost.chrono13', 1890)]);

        // Calculator mocké : retourne franco_reached SANS aucune ligne éligible
        // Si le composant recalculait (WeightCalculator), il retournerait franco_progress.
        $this->bindFakeCalculator([['type' => 'franco_reached']]);

        Livewire::test(ShippingOptions::class)
            ->assertSee('Votre commande est éligible')
            ->assertDontSee('articles éligibles pour bénéficier');
    }

    public function test_bandeau_franco_progress_affiche_montant_restant_depuis_quote(): void
    {
        $this->makeCartWithAddress();
        $this->bindManifestWith([$this->makeOption('chronopost.chrono13', 1890)]);

        $this->bindFakeCalculator([
            ['type' => 'franco_progress', 'remaining_cents' => 12500],
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSee('articles éligibles pour bénéficier')
            ->assertDontSee('Votre commande est éligible');
    }

    public function test_bandeau_excluded_lines_affiche_quand_quote_contient_excluded_lines(): void
    {
        $this->makeCartWithAddress();
        $this->bindManifestWith([$this->makeOption('chronopost.chrono13', 1890)]);

        $this->bindFakeCalculator([['type' => 'excluded_lines']]);

        Livewire::test(ShippingOptions::class)
            ->assertSee('frais de transport complémentaires');
    }

    public function test_bandeau_multi_colis_affiche_quand_quote_contient_multi_colis(): void
    {
        $this->makeCartWithAddress();
        $this->bindManifestWith([$this->makeOption('chronopost.chrono13', 1890)]);

        $this->bindFakeCalculator([['type' => 'multi_colis']]);

        Livewire::test(ShippingOptions::class)
            ->assertSee('expédiée en plusieurs colis');
    }

    public function test_aucun_bandeau_quand_quote_retourne_banners_vides(): void
    {
        $this->makeCartWithAddress();
        $this->bindManifestWith([$this->makeOption('chronopost.chrono13', 1890)]);

        $this->bindFakeCalculator([]);

        Livewire::test(ShippingOptions::class)
            ->assertDontSee('Votre commande est éligible')
            ->assertDontSee('articles éligibles pour bénéficier')
            ->assertDontSee('frais de transport complémentaires')
            ->assertDontSee('expédiée en plusieurs colis');
    }

    // ── Tests WeightCalculator (utilitaires — indépendants du composant) ──────

    public function test_weight_calculator_franco_eligible_subtotal(): void
    {
        $cart = $this->mockCart([
            $this->makeLine(francoEligible: true, subtotalHtCents: 60000),
        ]);

        $this->assertTrue(
            WeightCalculator::francoEligibleSubtotalHt($cart) >= config('shipping.franco.threshold_ht_cents')
            && ! WeightCalculator::cartHasFrancoExcludedLine($cart),
        );
    }

    public function test_weight_calculator_has_excluded_line(): void
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
        $this->bindPickupProviderWith([]);

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

    /**
     * Le client ne doit rien avoir à cliquer : choisir Chrono Relais précharge la
     * liste (et donc la carte) sur le code postal de l'adresse de livraison.
     */
    public function test_choisir_chrono_relais_precharge_les_points_sans_clic_sur_rechercher(): void
    {
        $this->makeCartWithAddress('34500');

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono13', 1890),
            $this->makeOption('chronopost.chrono_relais', 1490),
        ]);

        $this->bindPickupProviderWith([
            new PickupPoint(
                id: 'PR900',
                name: 'Relais Béziers',
                address1: '5 rue des Halles',
                postcode: '34500',
                city: 'Béziers',
                latitude: 43.34,
                longitude: 3.21,
            ),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSet('pickupPoints', [])
            ->set('chosenOption', 'chronopost.chrono_relais')
            ->assertSet('pickupSearchPostcode', '34500')
            ->assertCount('pickupPoints', 1)
            ->assertSet('pickupServiceUnavailable', false);
    }

    /**
     * Un point relais déjà retenu : la carte doit être là dès le montage, sans
     * repasser par le bouton « Rechercher ».
     */
    public function test_points_precharges_au_mount_quand_relais_deja_selectionne(): void
    {
        $cart = $this->makeCartWithAddress('34500');
        $cart->shippingAddress->update(['shipping_option' => 'chronopost.chrono_relais']);

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
        ]);

        $this->bindPickupProviderWith([
            new PickupPoint(
                id: 'PR900',
                name: 'Relais Béziers',
                address1: '5 rue des Halles',
                postcode: '34500',
                city: 'Béziers',
            ),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSet('chosenOption', 'chronopost.chrono_relais')
            ->assertCount('pickupPoints', 1);
    }

    /**
     * Service injoignable (credentials Chronopost absents, SOAP KO) : le client
     * doit le savoir. Auparavant le bouton « Rechercher » paraissait inerte —
     * même écran que pour une zone réellement sans point relais.
     */
    public function test_service_indisponible_est_signale_distinctement(): void
    {
        $this->makeCartWithAddress('34500');

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
        ]);
        $this->bindPickupProviderWith([], 'Chronopost pickup: missing account credentials');

        $component = Livewire::test(ShippingOptions::class)
            ->set('chosenOption', 'chronopost.chrono_relais')
            ->assertSet('pickupServiceUnavailable', true);

        $component->assertSee('momentanément indisponible');
    }

    public function test_aucun_point_trouve_est_signale_sans_parler_de_panne(): void
    {
        $this->makeCartWithAddress('34500');

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
        ]);
        $this->bindPickupProviderWith([]);

        Livewire::test(ShippingOptions::class)
            ->set('chosenOption', 'chronopost.chrono_relais')
            ->assertSet('pickupServiceUnavailable', false)
            ->assertSee('Aucun point relais trouvé')
            ->assertDontSee('momentanément indisponible');
    }

    public function test_saisie_manuelle_du_point_relais_est_persistee(): void
    {
        $cart = $this->makeCartWithAddress();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono_relais', 1490),
        ]);
        $this->bindPickupProviderWith([]);

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

    // ── Récap ventilé ─────────────────────────────────────────────────────────

    public function test_recap_ventile_affiche_quand_flat_price_present(): void
    {
        $this->makeCartWithAddress();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono13', 3890, false, [
                'grid_price_cents' => 3890,
                'flat_price_cents' => 8000,
                'surcharge_cents' => 0,
                'franco' => false,
            ]),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSet('chosenOption', 'chronopost.chrono13')
            ->assertSee('Total livraison HT');
    }

    public function test_recap_ventile_absent_quand_seul_prix_grille(): void
    {
        $this->makeCartWithAddress();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono13', 1890, false, [
                'grid_price_cents' => 1890,
                'flat_price_cents' => 0,
                'surcharge_cents' => 0,
                'franco' => false,
            ]),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertDontSee('Total livraison HT');
    }

    public function test_recap_ventile_affiche_quand_surcharge_presente(): void
    {
        $this->makeCartWithAddress();

        $this->bindManifestWith([
            $this->makeOption('chronopost.chrono13', 3890, false, [
                'grid_price_cents' => 3890,
                'flat_price_cents' => 0,
                'surcharge_cents' => 800,
                'franco' => false,
            ]),
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSee('Total livraison HT')
            ->assertSee('Supplément transport');
    }

    // ── Bandeau franco — non-régression (conservé pour compatibilité) ─────────

    public function test_bandeau_progression_franco_affiche_quand_seuil_non_atteint(): void
    {
        $this->makeCartWithAddress();
        $this->bindManifestWith([$this->makeOption('chronopost.chrono13', 1890)]);
        $this->bindFakeCalculator([
            ['type' => 'franco_progress', 'remaining_cents' => 50000],
        ]);

        Livewire::test(ShippingOptions::class)
            ->assertSee('articles éligibles pour bénéficier')
            ->assertDontSee('Votre commande est éligible');
    }

    public function test_bandeau_progression_franco_absent_quand_seuil_atteint(): void
    {
        $this->makeCartWithAddress();
        $this->bindManifestWith([$this->makeOption('chronopost.chrono13', 1890, franco: true)]);
        $this->bindFakeCalculator([['type' => 'franco_reached']]);

        Livewire::test(ShippingOptions::class)
            ->assertDontSee('articles éligibles pour bénéficier')
            ->assertSee('Votre commande est éligible');
    }

    // ── Test point relais (existant — inchangé) ───────────────────────────────

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

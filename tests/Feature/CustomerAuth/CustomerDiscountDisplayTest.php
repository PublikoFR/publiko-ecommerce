<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use App\Livewire\CartPage;
use App\Livewire\ProductPage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Lunar\DataTypes\Price;
use Lunar\Facades\CartSession;
use Lunar\Models\Brand;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Pko\CustomerAuth\Models\CustomerDiscount;
use Pko\CustomerAuth\Models\NegotiatedPrice;
use Pko\CustomerAuth\Pricing\CustomerDiscountResolver;
use Pko\Storefront\Livewire\CartDrawer;
use Pko\Storefront\Support\CustomerPriceDisplay;
use Tests\TestCase;

/**
 * Affichage front des remises client % : prix barré + badge « -X % » (fiche,
 * listings, panier), prix négocié seul affiché net sans barré, et même prix
 * résolu que la ligne de panier (donc commande, devis, facture).
 */
class CustomerDiscountDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = 'data-customer-discount';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @return array{0: User, 1: Customer} */
    private function makeCustomerUser(): array
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $customer->users()->attach($user);
        $customer->customerGroups()->attach(CustomerGroup::getDefault());

        return [$user->fresh(), $customer];
    }

    /** Variante d'un produit de marque dédiée, prix de base unique. */
    private function makeVariant(int $baseCents, ?Brand $brand = null): ProductVariant
    {
        $product = Product::factory()->create();
        DB::table('lunar_products')->where('id', $product->id)->update([
            'brand_id' => ($brand ?? Brand::factory()->create())->id,
        ]);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->prices()->create([
            'price' => $baseCents,
            'currency_id' => Currency::getDefault()->id,
            'min_quantity' => 1,
        ]);

        return $variant->fresh();
    }

    private function brandDiscount(Customer $customer, ProductVariant $variant, string $percent): void
    {
        CustomerDiscount::create([
            'customer_id' => $customer->id,
            'target_type' => CustomerDiscount::TARGET_BRAND,
            'target_id' => (int) DB::table('lunar_products')->where('id', $variant->product_id)->value('brand_id'),
            'percent' => $percent,
        ]);
        app(CustomerDiscountResolver::class)->flush();
    }

    private function money(int $cents): string
    {
        return (new Price($cents, Currency::getDefault(), 1))->formatted();
    }

    private function renderGate(ProductVariant $variant): string
    {
        return Blade::render('<x-storefront.price-gate :variant="$variant" size="md" />', ['variant' => $variant]);
    }

    private function makeCartWith(User $user, ProductVariant $variant, int $quantity): Cart
    {
        $cart = Cart::factory()->create([
            'currency_id' => Currency::getDefault()->id,
            'channel_id' => Channel::query()->where('default', true)->firstOrFail()->id,
            'user_id' => $user->id,
        ]);
        CartSession::use($cart);
        $cart->add($variant, $quantity);

        return CartSession::current();
    }

    // ── Composant prix (fiche, cartes de listing, recherche) ────────────────

    public function test_remise_client_affiche_prix_barre_et_badge(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $variant = $this->makeVariant(10000);
        $this->brandDiscount($customer, $variant, '12.5');

        $this->actingAs($user);
        $html = $this->renderGate($variant);

        $this->assertStringContainsString(self::MARKER, $html);
        $this->assertStringContainsString('<s', $html);
        $this->assertStringContainsString($this->money(10000), $html);
        $this->assertStringContainsString($this->money(8750), $html);
        $this->assertStringContainsString("-12,5\u{00A0}%", $html);
    }

    public function test_prix_negocie_seul_affiche_net_sans_barre(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $variant = $this->makeVariant(10000);
        NegotiatedPrice::create([
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::getDefault()->id,
            'price' => 7000,
        ]);

        $this->actingAs($user);
        $html = $this->renderGate($variant);

        $this->assertStringContainsString($this->money(7000), $html);
        $this->assertStringNotContainsString($this->money(10000), $html);
        $this->assertStringNotContainsString(self::MARKER, $html);
    }

    public function test_prix_negocie_plus_bas_que_la_remise_supprime_le_badge(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $variant = $this->makeVariant(10000);
        $this->brandDiscount($customer, $variant, '10');
        NegotiatedPrice::create([
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::getDefault()->id,
            'price' => 8000,
        ]);

        $this->actingAs($user);
        $html = $this->renderGate($variant);

        $this->assertStringContainsString($this->money(8000), $html);
        $this->assertStringNotContainsString(self::MARKER, $html);
    }

    public function test_produit_non_remisable_sans_badge(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $variant = $this->makeVariant(10000);
        DB::table('lunar_products')->where('id', $variant->product_id)->update(['pko_not_discountable' => true]);
        $this->brandDiscount($customer, $variant, '20');

        $this->actingAs($user);
        $html = $this->renderGate($variant);

        $this->assertStringContainsString($this->money(10000), $html);
        $this->assertStringNotContainsString(self::MARKER, $html);
    }

    public function test_fiche_produit_affiche_la_remise(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $product = Product::query()->has('urls')->has('variants')->with(['defaultUrl', 'variants'])->firstOrFail();
        $brand = Brand::factory()->create();
        DB::table('lunar_products')->where('id', $product->id)->update(['brand_id' => $brand->id]);
        $this->brandDiscount($customer, $product->variants->first(), '15');

        $this->actingAs($user);

        Livewire::test(ProductPage::class, ['slug' => $product->defaultUrl->slug])
            ->assertSeeHtml(self::MARKER)
            ->assertSee("-15\u{00A0}%");
    }

    public function test_preload_listing_ne_change_pas_le_resultat(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $brand = Brand::factory()->create();
        $a = $this->makeVariant(10000, $brand);
        $b = $this->makeVariant(5000, $brand);
        $this->brandDiscount($customer, $a, '20');

        $this->actingAs($user);
        CustomerPriceDisplay::preloadProducts(Product::query()->whereIn('id', [$a->product_id, $b->product_id])->get());

        $this->assertSame(8000, CustomerPriceDisplay::forVariant($a)->price->value);
        $this->assertSame(4000, CustomerPriceDisplay::forVariant($b)->price->value);
        $this->assertSame("-20\u{00A0}%", CustomerPriceDisplay::forVariant($b)->badgeLabel());
    }

    public function test_invite_ne_voit_pas_de_prix(): void
    {
        $variant = $this->makeVariant(10000);

        $html = $this->renderGate($variant);

        $this->assertStringNotContainsString($this->money(10000), $html);
        $this->assertStringNotContainsString(self::MARKER, $html);
    }

    // ── Panier : même prix résolu que la ligne facturée ─────────────────────

    public function test_panier_affiche_barre_et_badge_sur_le_prix_de_ligne(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $variant = $this->makeVariant(10000);
        $this->brandDiscount($customer, $variant, '15');

        $this->actingAs($user);
        $cart = $this->makeCartWith($user, $variant, 2);

        // Prix facturé (commande, devis, Pennylane lisent cette valeur figée).
        $line = $cart->lines->first();
        $this->assertSame(8500, (int) $line->unitPrice->value);
        $this->assertSame(17000, (int) $cart->subTotal->value);

        Livewire::test(CartPage::class)
            ->assertSet('lines.0.unit_price', $this->money(8500))
            ->assertSet('lines.0.unit_original', $this->money(10000))
            ->assertSet('lines.0.discount_badge', "-15\u{00A0}%")
            ->assertSee("-15\u{00A0}%");

        Livewire::test(CartDrawer::class)
            ->assertSet('lines.0.unit_original', $this->money(10000))
            ->assertSet('lines.0.discount_badge', "-15\u{00A0}%");
    }

    public function test_panier_prix_negocie_sans_barre(): void
    {
        [$user, $customer] = $this->makeCustomerUser();
        $variant = $this->makeVariant(10000);
        NegotiatedPrice::create([
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::getDefault()->id,
            'price' => 7000,
        ]);

        $this->actingAs($user);
        $cart = $this->makeCartWith($user, $variant, 1);
        $this->assertSame(7000, (int) $cart->lines->first()->unitPrice->value);

        Livewire::test(CartPage::class)
            ->assertSet('lines.0.unit_price', $this->money(7000))
            ->assertSet('lines.0.unit_original', null)
            ->assertSet('lines.0.discount_badge', null);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Ecotax;

use App\Livewire\CartPage;
use App\Livewire\ProductPage;
use App\Models\User;
use App\Support\Ecotax;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Lunar\Stripe\Facades\Stripe;
use Pko\Account\Livewire\OrderDetailPage;
use Pko\ShippingCommon\Mail\QuotePaymentLinkMail;
use Pko\Storefront\Livewire\CartDrawer;
use Tests\TestCase;

/**
 * Affichage « dont éco-participation X € » (FEAT 003 V3, docs/ecotax.md § Affichage).
 */
class EcotaxDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const LABEL = 'dont éco-participation';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ── Fiche produit ───────────────────────────────────────────────────────

    public function test_fiche_produit_affiche_l_eco_participation_de_la_variante(): void
    {
        $product = $this->productWithUrl();
        $variant = $product->variants->first();
        $variant->pko_ecotax_cents = 160;
        $variant->save();

        $this->actingAs(User::factory()->create());

        Livewire::test(ProductPage::class, ['slug' => $product->defaultUrl->slug])
            ->assertSee(self::LABEL)
            ->assertSee(Ecotax::format(160));
    }

    public function test_fiche_produit_sans_eco_taxe_n_affiche_rien(): void
    {
        $product = $this->productWithUrl();
        foreach ($product->variants as $variant) {
            $variant->pko_ecotax_cents = null;
            $variant->save();
        }

        $this->actingAs(User::factory()->create());

        Livewire::test(ProductPage::class, ['slug' => $product->defaultUrl->slug])
            ->assertDontSee(self::LABEL);
    }

    public function test_fiche_produit_suit_le_changement_de_variante(): void
    {
        $product = $this->productWithUrl();
        [$small, $large, $optionId, $smallValueId, $largeValueId] = $this->twoVariantsWithOptions($product);

        $small->pko_ecotax_cents = 120;
        $small->save();
        $large->pko_ecotax_cents = 450;
        $large->save();

        $this->actingAs(User::factory()->create());

        // L'ajout d'une variante (nouveau SKU) peut régénérer les URLs : relire la courante.
        $slug = $product->fresh('defaultUrl')->defaultUrl->slug;

        Livewire::test(ProductPage::class, ['slug' => $slug])
            ->set('selectedOptionValues', [$optionId => $smallValueId])
            ->assertSee(Ecotax::format(120))
            ->assertDontSee(Ecotax::format(450))
            ->set('selectedOptionValues', [$optionId => $largeValueId])
            ->assertSee(Ecotax::format(450))
            ->assertDontSee(Ecotax::format(120));
    }

    // ── Panier ──────────────────────────────────────────────────────────────

    public function test_panier_affiche_la_ligne_et_le_total(): void
    {
        $variant = ProductVariant::query()->first();
        $variant->pko_ecotax_cents = 150;
        $variant->save();

        $this->makeCartWith($variant, 2);

        // Ligne : 150 × 2 = 300 ; une seule ligne → le total vaut aussi 300.
        Livewire::test(CartPage::class)
            ->assertSee(self::LABEL)
            ->assertSee(Ecotax::format(300))
            ->assertSet('lines.0.ecotax', Ecotax::format(300));

        Livewire::test(CartDrawer::class)
            ->assertSet('lines.0.ecotax', Ecotax::format(300))
            ->assertSet('ecotaxTotal', Ecotax::format(300))
            ->assertSee(self::LABEL);
    }

    public function test_panier_reagit_au_changement_de_quantite(): void
    {
        $variant = ProductVariant::query()->first();
        $variant->pko_ecotax_cents = 150;
        $variant->save();

        $cart = $this->makeCartWith($variant, 2);
        $lineId = $cart->lines->first()->id;

        Livewire::test(CartPage::class)
            ->call('updateQuantity', $lineId, 3)
            ->assertSet('lines.0.ecotax', Ecotax::format(450))
            ->assertSee(Ecotax::format(450));
    }

    public function test_panier_sans_eco_taxe_n_affiche_rien(): void
    {
        $variant = ProductVariant::query()->first();
        $variant->pko_ecotax_cents = 0;
        $variant->save();

        $this->makeCartWith($variant, 2);

        Livewire::test(CartPage::class)
            ->assertSet('lines.0.ecotax', null)
            ->assertDontSee(self::LABEL);

        Livewire::test(CartDrawer::class)
            ->assertSet('ecotaxTotal', null)
            ->assertDontSee(self::LABEL);
    }

    // ── Commande passée : valeur figée ─────────────────────────────────────

    public function test_detail_commande_utilise_la_valeur_figee_meme_si_la_variante_a_change(): void
    {
        [$customer, $user] = $this->makeCustomerWithUser();
        $variant = ProductVariant::query()->first();
        $order = $this->makeOrder(customer: $customer, variant: $variant, frozenEcotax: 160);

        // L'éco-taxe de la variante a changé depuis la commande.
        $variant->pko_ecotax_cents = 990;
        $variant->save();

        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['order' => $order])
            ->assertSee(self::LABEL)
            ->assertSee(Ecotax::format(320))   // ligne : 160 × 2 (et total)
            ->assertDontSee(Ecotax::format(990))
            ->assertDontSee(Ecotax::format(1980));
    }

    public function test_detail_commande_sans_valeur_figee_n_affiche_rien(): void
    {
        [$customer, $user] = $this->makeCustomerWithUser();
        $variant = ProductVariant::query()->first();
        $variant->pko_ecotax_cents = 990;
        $variant->save();
        $order = $this->makeOrder(customer: $customer, variant: $variant, frozenEcotax: null);

        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['order' => $order])
            ->assertDontSee(self::LABEL);
    }

    // ── Devis ───────────────────────────────────────────────────────────────

    public function test_page_de_devis_affiche_la_ligne_et_le_total_figes(): void
    {
        config([
            'services.stripe.key' => 'sk_test_dummy',
            'services.stripe.public_key' => 'pk_test_dummy',
        ]);
        Stripe::fake();
        Queue::fake();

        $order = $this->makeOrder(variant: ProductVariant::query()->first(), frozenEcotax: 160, status: 'awaiting-quote');

        $url = URL::signedRoute('pko.quote.pay', [
            'order' => $order->id,
            'transport_cents' => 1490,
        ], now()->addDays(7));

        $this->get($url)
            ->assertOk()
            ->assertSee(self::LABEL.' 3,20 € HT', false);
    }

    public function test_mail_de_devis_affiche_l_eco_participation(): void
    {
        $order = $this->makeOrder(variant: ProductVariant::query()->first(), frozenEcotax: 160, status: 'awaiting-quote');

        $html = (new QuotePaymentLinkMail($order, 'https://example.test/pay', 1490))->render();

        $this->assertStringContainsString(self::LABEL.' 3,20 € HT', $html);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function productWithUrl(): Product
    {
        $product = Product::query()->has('urls')->has('variants')->with(['defaultUrl', 'variants'])->first();
        $this->assertNotNull($product, 'Le seeder doit fournir un produit avec URL.');

        return $product;
    }

    /**
     * Deux variantes S / L sur le produit, distinguées par une option.
     *
     * @return array{0: ProductVariant, 1: ProductVariant, 2: int, 3: int, 4: int}
     */
    private function twoVariantsWithOptions(Product $product): array
    {
        $option = ProductOption::create([
            'name' => ['fr' => 'Taille', 'en' => 'Size'],
            'label' => ['fr' => 'Taille', 'en' => 'Size'],
            'handle' => 'taille-ecotax-test',
            'shared' => false,
        ]);
        $s = ProductOptionValue::create(['product_option_id' => $option->id, 'name' => ['fr' => 'S', 'en' => 'S'], 'position' => 1]);
        $l = ProductOptionValue::create(['product_option_id' => $option->id, 'name' => ['fr' => 'L', 'en' => 'L'], 'position' => 2]);

        // Une seule variante de référence, clonée avec ses prix.
        $small = $product->variants->first();
        $product->variants()->where('id', '!=', $small->id)->get()->each->delete();

        $large = $small->replicate();
        $large->sku = $small->sku.'-L';
        $large->save();
        foreach ($small->prices as $price) {
            $copy = $price->replicate();
            $copy->priceable_id = $large->id;
            $copy->save();
        }

        $small->values()->sync([$s->id]);
        $large->values()->sync([$l->id]);

        return [$small, $large, $option->id, $s->id, $l->id];
    }

    private function makeCartWith(ProductVariant $variant, int $quantity): Cart
    {
        $currency = Currency::query()->where('default', true)->firstOrFail();
        $channel = Channel::query()->where('default', true)->firstOrFail();

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
            'channel_id' => $channel->id,
        ]);
        CartSession::use($cart);
        $cart->add($variant, $quantity);

        return CartSession::current();
    }

    /** @return array{0: Customer, 1: User} */
    private function makeCustomerWithUser(): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();

        DB::table('lunar_customer_user')->insert([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        return [$customer, $user];
    }

    private function makeOrder(
        ProductVariant $variant,
        ?int $frozenEcotax,
        ?Customer $customer = null,
        string $status = 'payment-received',
    ): Order {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        $id = DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'customer_id' => $customer?->id,
            'status' => $status,
            'reference' => 'ECO-'.uniqid(),
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'exchange_rate' => 1,
            'sub_total' => 10000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 10000,
            'tax_breakdown' => '[]',
            'discount_breakdown' => '[]',
            'shipping_breakdown' => '[]',
            'meta' => '[]',
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lunar_order_lines')->insert([
            'order_id' => $id,
            'purchasable_type' => (new ProductVariant)->getMorphClass(),
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => 'Produit éco-taxé',
            'identifier' => 'ECO-SKU',
            'unit_price' => 5000,
            'unit_quantity' => 1,
            'quantity' => 2,
            'sub_total' => 10000,
            'discount_total' => 0,
            'tax_breakdown' => '[]',
            'tax_total' => 0,
            'total' => 10000,
            'meta' => json_encode($frozenEcotax !== null ? ['ecotax_cents' => $frozenEcotax] : []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }
}

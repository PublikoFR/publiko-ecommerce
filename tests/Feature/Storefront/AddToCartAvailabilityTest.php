<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Components\AddToCart;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Facades\CartSession;
use Lunar\Models\ProductVariant;
use Pko\Storefront\Support\VariantAvailability;
use Tests\TestCase;

/**
 * Vente sur stock fournisseur : une variante `purchasable = always` reste
 * commandable à stock zéro (badge « Sur commande »). Un contrôle naïf
 * `stock < quantity` bloquait toute cette catégorie de produits.
 */
class AddToCartAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->actingAs(User::factory()->create());
    }

    public function test_un_produit_sur_commande_peut_etre_ajoute_au_panier(): void
    {
        $variant = $this->makeVariant(stock: 0, purchasable: 'always');

        Livewire::test(AddToCart::class, ['purchasable' => $variant])
            ->set('quantity', 3)
            ->call('addToCart')
            ->assertHasNoErrors();

        $this->assertSame(3, (int) CartSession::current()->lines->sum('quantity'));
    }

    public function test_une_variante_in_stock_refuse_au_dela_du_stock(): void
    {
        $variant = $this->makeVariant(stock: 2, purchasable: 'in_stock');

        Livewire::test(AddToCart::class, ['purchasable' => $variant])
            ->set('quantity', 5)
            ->call('addToCart')
            ->assertHasErrors('quantity');
    }

    public function test_une_variante_in_stock_accepte_dans_la_limite_du_stock(): void
    {
        $variant = $this->makeVariant(stock: 2, purchasable: 'in_stock');

        Livewire::test(AddToCart::class, ['purchasable' => $variant])
            ->set('quantity', 2)
            ->call('addToCart')
            ->assertHasNoErrors();
    }

    public function test_le_badge_ne_promet_sur_commande_que_si_le_panier_laccepte(): void
    {
        $this->assertSame(
            'Sur commande',
            VariantAvailability::for($this->makeVariant(stock: 0, purchasable: 'always'))['label'],
        );

        // Épuisé : ni stock, ni approvisionnement — annoncer « Sur commande » ici
        // promettait une commande que AddToCart refuse ensuite.
        $epuise = VariantAvailability::for($this->makeVariant(stock: 0, purchasable: 'in_stock'));
        $this->assertSame('Épuisé', $epuise['label']);
        $this->assertFalse($epuise['orderable']);

        $this->assertSame(
            'Stock limité',
            VariantAvailability::for($this->makeVariant(stock: 3, purchasable: 'in_stock'))['label'],
        );

        $this->assertSame(
            'En stock',
            VariantAvailability::for($this->makeVariant(stock: 40, purchasable: 'always'))['label'],
        );
    }

    private function makeVariant(int $stock, string $purchasable): ProductVariant
    {
        $variant = ProductVariant::query()->first();
        $this->assertNotNull($variant, 'Le seeder doit fournir au moins une variante.');

        $variant->forceFill([
            'stock' => $stock,
            'backorder' => 0,
            'purchasable' => $purchasable,
        ])->save();

        return $variant->refresh();
    }
}

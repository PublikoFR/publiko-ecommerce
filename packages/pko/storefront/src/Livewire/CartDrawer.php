<?php

declare(strict_types=1);

namespace Pko\Storefront\Livewire;

use App\Support\Ecotax;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Lunar\Facades\CartSession;
use Lunar\Models\CartLine;
use Lunar\Models\Contracts\Currency as CurrencyContract;
use Pko\Storefront\Support\CustomerPriceDisplay;

class CartDrawer extends Component
{
    public bool $open = false;

    public array $lines = [];

    public ?string $subTotal = null;

    public ?string $discountTotal = null;

    public ?string $couponCode = null;

    /** @var array{winner: string, headline: string, details: list<string>}|null */
    public ?array $advantage = null;

    public ?string $total = null;

    /** Éco-participation totale du panier, formatée (incluse dans le sous-total) ; null si nulle. */
    public ?string $ecotaxTotal = null;

    public int $linesCount = 0;

    public function mount(): void
    {
        $this->refreshCart();
    }

    #[On('add-to-cart')]
    public function handleAddToCart(): void
    {
        $this->refreshCart();
        $this->open = true;
    }

    #[On('cartUpdated')]
    public function handleCartUpdated(): void
    {
        $this->refreshCart();
    }

    #[On('open-cart-drawer')]
    public function openDrawer(): void
    {
        $this->refreshCart();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function updateQuantity(int $lineId, int $quantity): void
    {
        if ($quantity < 1) {
            $this->removeLine($lineId);

            return;
        }
        CartSession::updateLines(collect([['id' => $lineId, 'quantity' => min(10000, $quantity)]]));
        $this->refreshCart();
        $this->dispatch('cartUpdated');
    }

    public function removeLine(int $lineId): void
    {
        CartSession::remove($lineId);
        $this->refreshCart();
        $this->dispatch('cartUpdated');
    }

    private function refreshCart(): void
    {
        try {
            $cart = CartSession::current();
        } catch (\Throwable) {
            $cart = null;
        }

        if ($cart === null) {
            $this->lines = [];
            $this->subTotal = null;
            $this->discountTotal = null;
            $this->couponCode = null;
            $this->advantage = null;
            $this->total = null;
            $this->ecotaxTotal = null;
            $this->linesCount = 0;

            return;
        }

        $this->lines = $cart->lines->map(fn ($line) => [
            ...self::discountFields($line),
            'id' => $line->id,
            'description' => $line->purchasable->getDescription(),
            'identifier' => $line->purchasable->getIdentifier(),
            'quantity' => $line->quantity,
            'unit_price' => $line->unitPrice->formatted(),
            'sub_total' => $line->subTotal->formatted(),
            'thumbnail' => $line->purchasable->getThumbnail()?->getUrl('small'),
            'ecotax' => self::formatLineEcotax($line, $cart->currency),
        ])->toArray();

        $this->linesCount = count($this->lines);
        $this->subTotal = $cart->subTotal?->formatted();
        $this->discountTotal = ($cart->discountTotal?->value ?? 0) > 0
            ? $cart->discountTotal->formatted()
            : null;
        $this->couponCode = $cart->coupon_code;
        // Non-cumul remise client / promotions (package customer-auth, optionnel).
        $this->advantage = class_exists(\Pko\CustomerAuth\Pricing\CartDiscountArbiter::class)
            ? app(\Pko\CustomerAuth\Pricing\CartDiscountArbiter::class)->displayFor($cart)
            : null;
        $this->total = $cart->total?->formatted();
        $ecotaxCents = Ecotax::forCart($cart);
        $this->ecotaxTotal = $ecotaxCents > 0 ? Ecotax::format($ecotaxCents, $cart->currency) : null;
    }

    /**
     * Remise client % de la ligne : prix unitaire avant remise (barré) + badge.
     *
     * @return array{unit_original: ?string, discount_badge: ?string}
     */
    private static function discountFields(CartLine $line): array
    {
        $display = CustomerPriceDisplay::forCartLine($line);

        return [
            'unit_original' => $display->original?->formatted(),
            'discount_badge' => $display->badgeLabel(),
        ];
    }

    /** Éco-participation de la ligne (incluse dans le prix), formatée ; null si nulle. */
    private static function formatLineEcotax(CartLine $line, ?CurrencyContract $currency): ?string
    {
        $cents = Ecotax::forCartLine($line);

        return $cents > 0 ? Ecotax::format($cents, $currency) : null;
    }

    public function render(): View
    {
        return view('storefront::livewire.cart-drawer');
    }
}

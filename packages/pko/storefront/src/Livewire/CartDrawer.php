<?php

declare(strict_types=1);

namespace Pko\Storefront\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Lunar\Facades\CartSession;

class CartDrawer extends Component
{
    public bool $open = false;

    public array $lines = [];

    public ?string $subTotal = null;

    public ?string $total = null;

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
            $this->total = null;
            $this->linesCount = 0;

            return;
        }

        $this->lines = $cart->lines->map(fn ($line) => [
            'id' => $line->id,
            'description' => $line->purchasable->getDescription(),
            'identifier' => $line->purchasable->getIdentifier(),
            'quantity' => $line->quantity,
            'unit_price' => $line->unitPrice->formatted(),
            'sub_total' => $line->subTotal->formatted(),
            'thumbnail' => $line->purchasable->getThumbnail()?->getUrl('small'),
        ])->toArray();

        $this->linesCount = count($this->lines);
        $this->subTotal = $cart->subTotal?->formatted();
        $this->total = $cart->total?->formatted();
    }

    public function render(): View
    {
        return view('storefront::livewire.cart-drawer');
    }
}

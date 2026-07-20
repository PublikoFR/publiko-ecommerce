<?php

declare(strict_types=1);

namespace Pko\Storefront\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Lunar\Facades\CartSession;

class CartBadge extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    #[On('cartUpdated')]
    #[On('add-to-cart')]
    #[On('open-cart-drawer')]
    public function refreshCount(): void
    {
        try {
            $cart = CartSession::current();
        } catch (\Throwable) {
            $cart = null;
        }

        $this->count = (int) ($cart?->lines()->count() ?? 0);
    }

    public function render(): View
    {
        return view('storefront::livewire.cart-badge');
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Lunar\Facades\CartSession;
use Pko\ShippingCommon\Models\Supplier;

class CartPage extends Component
{
    public array $lines = [];

    protected $listeners = ['cartUpdated' => 'refresh'];

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $cart = CartSession::current();
        $this->lines = $cart?->lines->map(fn ($line) => [
            'id' => $line->id,
            'description' => $line->purchasable->getDescription(),
            'identifier' => $line->purchasable->getIdentifier(),
            'quantity' => $line->quantity,
            'unit_price' => $line->unitPrice->formatted(),
            'sub_total' => $line->subTotal->formatted(),
            'thumbnail' => $line->purchasable->getThumbnail()?->getUrl('small'),
            'availability' => $this->resolveAvailability($line->purchasable),
        ])->toArray() ?? [];
    }

    private function resolveAvailability(mixed $variant): array
    {
        if ($variant === null) {
            return ['status' => 'weklo'];
        }

        if (($variant->stock ?? 0) > 0) {
            return ['status' => 'weklo'];
        }

        $supplierId = $variant->product?->pko_supplier_id;
        if ($supplierId !== null) {
            $supplier = Supplier::find($supplierId);

            return [
                'status' => 'supplier',
                'lead_min' => $supplier?->lead_time_min_days,
                'lead_max' => $supplier?->lead_time_max_days,
            ];
        }

        return ['status' => 'weklo'];
    }

    public function updateQuantity(int $lineId, int $quantity): void
    {
        if ($quantity < 1) {
            $this->remove($lineId);

            return;
        }

        CartSession::updateLines(collect([['id' => $lineId, 'quantity' => min(10000, $quantity)]]));
        $this->refresh();
        $this->dispatch('cartUpdated');
    }

    public function remove(int $lineId): void
    {
        CartSession::remove($lineId);
        $this->refresh();
        $this->dispatch('cartUpdated');
    }

    public function clear(): void
    {
        $cart = CartSession::current();
        if ($cart) {
            foreach ($cart->lines as $line) {
                CartSession::remove($line->id);
            }
        }
        $this->refresh();
        $this->dispatch('cartUpdated');
    }

    public function getCartProperty()
    {
        return CartSession::current();
    }

    public function getHasMixedCartProperty(): bool
    {
        $cart = CartSession::current();
        if (! $cart) {
            return false;
        }

        $lines = $cart->lines->loadMissing('purchasable.product')
            ->filter(fn ($l) => $l->type === 'physical');
        $hasQuote = $lines->contains(fn ($l) => ($l->purchasable?->product?->pko_port_mode ?? '') === 'quote');
        $hasNonQuote = $lines->contains(fn ($l) => ($l->purchasable?->product?->pko_port_mode ?? '') !== 'quote');

        return $hasQuote && $hasNonQuote;
    }

    public function getQuoteLineCountProperty(): int
    {
        $cart = CartSession::current();
        if (! $cart) {
            return 0;
        }

        return $cart->lines
            ->loadMissing('purchasable.product')
            ->filter(fn ($l) => ($l->purchasable?->product?->pko_port_mode ?? '') === 'quote')
            ->count();
    }

    #[Layout('layouts.storefront')]
    public function render(): View
    {
        return view('livewire.cart-page');
    }
}

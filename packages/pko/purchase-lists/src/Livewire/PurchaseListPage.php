<?php

declare(strict_types=1);

namespace Pko\PurchaseLists\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Lunar\Facades\CartSession;
use Pko\Account\Support\AccountContext;
use Pko\PurchaseLists\Models\PurchaseList;

class PurchaseListPage extends Component
{
    public PurchaseList $list;

    public string $name = '';

    public ?string $notes = null;

    public function mount(PurchaseList $list): void
    {
        $customer = AccountContext::customer();
        abort_unless($customer !== null && (int) $list->customer_id === (int) $customer->id, 404);

        $this->list = $list->load('items.purchasable');
        $this->name = (string) $list->name;
        $this->notes = $list->notes;
    }

    public function save(): void
    {
        $this->validate(['name' => 'required|string|max:120', 'notes' => 'nullable|string|max:5000']);
        $this->list->forceFill(['name' => $this->name, 'notes' => $this->notes])->save();
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        $item = $this->list->items()->find($itemId);
        if ($item === null) {
            return;
        }
        if ($quantity < 1) {
            $item->delete();
        } else {
            $item->forceFill(['quantity' => min(10000, $quantity)])->save();
        }
        $this->list->refresh()->load('items.purchasable');
    }

    public function removeItem(int $itemId): void
    {
        $this->list->items()->whereKey($itemId)->delete();
        $this->list->refresh()->load('items.purchasable');
    }

    public function addAllToCart(): void
    {
        $manager = CartSession::manager();
        foreach ($this->list->items as $item) {
            if ($item->purchasable) {
                $manager->add($item->purchasable, (int) $item->quantity);
            }
        }
        $this->dispatch('cartUpdated');
        session()->flash('status', 'Articles ajoutés au panier.');
    }

    #[Layout('account::layouts.account')]
    public function render(): View
    {
        return view('purchase-lists::livewire.detail');
    }
}

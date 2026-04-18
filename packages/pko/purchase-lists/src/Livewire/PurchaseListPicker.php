<?php

declare(strict_types=1);

namespace Pko\PurchaseLists\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Pko\Account\Support\AccountContext;
use Pko\PurchaseLists\Models\PurchaseList;
use Pko\PurchaseLists\Models\PurchaseListItem;

class PurchaseListPicker extends Component
{
    public bool $open = false;

    public ?int $purchasableId = null;

    public ?string $purchasableType = null;

    public string $newListName = '';

    public ?string $flash = null;

    #[On('open-purchase-list-picker')]
    public function openPicker(int $id, string $type): void
    {
        $this->purchasableId = $id;
        $this->purchasableType = $type;
        $this->open = true;
        $this->flash = null;
    }

    public function addToExisting(int $listId): void
    {
        $customer = AccountContext::customer();
        abort_unless($customer, 403);

        $list = PurchaseList::where('customer_id', $customer->id)->findOrFail($listId);
        $this->addToList($list);
        $this->flash = 'Ajouté à "'.$list->name.'"';
    }

    public function createAndAdd(): void
    {
        $this->validate(['newListName' => 'required|string|max:120']);
        $customer = AccountContext::customer();
        abort_unless($customer, 403);

        $list = PurchaseList::create(['customer_id' => $customer->id, 'name' => $this->newListName]);
        $this->addToList($list);
        $this->newListName = '';
        $this->flash = 'Liste "'.$list->name.'" créée et mise à jour.';
    }

    private function addToList(PurchaseList $list): void
    {
        $existing = $list->items()
            ->where('purchasable_type', $this->purchasableType)
            ->where('purchasable_id', $this->purchasableId)
            ->first();

        if ($existing) {
            $existing->increment('quantity');
        } else {
            PurchaseListItem::create([
                'purchase_list_id' => $list->id,
                'purchasable_type' => $this->purchasableType,
                'purchasable_id' => $this->purchasableId,
                'quantity' => 1,
            ]);
        }
    }

    public function render(): View
    {
        $customer = AccountContext::customer();
        $lists = $customer
            ? PurchaseList::where('customer_id', $customer->id)->withCount('items')->orderBy('name')->get()
            : collect();

        return view('purchase-lists::livewire.picker', ['lists' => $lists]);
    }
}

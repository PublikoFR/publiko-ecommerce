<?php

declare(strict_types=1);

namespace Pko\PurchaseLists\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Pko\Account\Support\AccountContext;
use Pko\PurchaseLists\Models\PurchaseList;

class PurchaseListsPage extends Component
{
    public string $newListName = '';

    public function createList(): void
    {
        $this->validate(['newListName' => 'required|string|max:120']);

        $customer = AccountContext::customer();
        abort_unless($customer, 403);

        PurchaseList::create([
            'customer_id' => $customer->id,
            'name' => $this->newListName,
        ]);

        $this->newListName = '';
    }

    public function deleteList(int $id): void
    {
        $customer = AccountContext::customer();
        abort_unless($customer, 403);

        PurchaseList::where('customer_id', $customer->id)->where('id', $id)->delete();
    }

    #[Layout('account::layouts.account')]
    public function render(): View
    {
        $customer = AccountContext::customer();
        $lists = $customer
            ? PurchaseList::withCount('items')->where('customer_id', $customer->id)->orderByDesc('id')->get()
            : collect();

        return view('purchase-lists::livewire.index', ['lists' => $lists]);
    }
}

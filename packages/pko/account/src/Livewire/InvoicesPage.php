<?php

declare(strict_types=1);

namespace Pko\Account\Livewire;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Pko\Account\Contracts\CustomerInvoices;
use Pko\Account\Support\AccountContext;

class InvoicesPage extends Component
{
    use WithPagination;

    public function getInvoicesProperty(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $customer = AccountContext::customer();

        return $customer
            ? app(CustomerInvoices::class)->forCustomer((int) $customer->id)
            : new LengthAwarePaginator([], 0, 10);
    }

    #[Layout('account::layouts.account', ['sidebar' => false])]
    public function render(): View
    {
        return view('account::livewire.invoices-page');
    }
}

<?php

declare(strict_types=1);

namespace Pko\Account\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Pko\Account\Support\AccountContext;

class AddressesPage extends Component
{
    #[Layout('account::layouts.account')]
    public function render(): View
    {
        $customer = AccountContext::customer();

        return view('account::livewire.addresses-page', [
            'customer' => $customer,
            'addresses' => $customer ? $customer->addresses()->get() : collect(),
        ]);
    }
}

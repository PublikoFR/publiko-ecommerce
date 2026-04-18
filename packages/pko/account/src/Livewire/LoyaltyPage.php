<?php

declare(strict_types=1);

namespace Pko\Account\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Pko\Account\Support\AccountContext;
use Pko\Loyalty\Services\LoyaltyManager;

class LoyaltyPage extends Component
{
    #[Layout('account::layouts.account')]
    public function render(): View
    {
        $customer = AccountContext::customer();
        $snapshot = null;

        if ($customer !== null && class_exists(LoyaltyManager::class)) {
            try {
                $snapshot = app(LoyaltyManager::class)->getCustomerSnapshot($customer);
            } catch (\Throwable) {
                $snapshot = null;
            }
        }

        return view('account::livewire.loyalty-page', [
            'customer' => $customer,
            'snapshot' => $snapshot,
        ]);
    }
}

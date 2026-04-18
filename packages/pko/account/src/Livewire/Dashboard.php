<?php

declare(strict_types=1);

namespace Pko\Account\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Lunar\Models\Order;
use Pko\Account\Support\AccountContext;

class Dashboard extends Component
{
    #[Layout('account::layouts.account')]
    public function render(): View
    {
        $customer = AccountContext::customer();

        $recentOrders = $customer
            ? Order::query()->where('customer_id', $customer->id)->orderByDesc('placed_at')->limit(3)->get()
            : collect();

        return view('account::livewire.dashboard', [
            'customer' => $customer,
            'user' => AccountContext::user(),
            'recentOrders' => $recentOrders,
        ]);
    }
}

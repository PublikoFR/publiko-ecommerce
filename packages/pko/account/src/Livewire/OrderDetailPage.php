<?php

declare(strict_types=1);

namespace Pko\Account\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Lunar\Models\Order;
use Pko\Account\Support\AccountContext;

class OrderDetailPage extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $customer = AccountContext::customer();
        abort_unless($customer !== null && (int) $order->customer_id === (int) $customer->id, 404);

        $this->order = $order->load(['lines', 'shippingAddress', 'billingAddress']);
    }

    #[Layout('account::layouts.account')]
    public function render(): View
    {
        return view('account::livewire.order-detail-page');
    }
}

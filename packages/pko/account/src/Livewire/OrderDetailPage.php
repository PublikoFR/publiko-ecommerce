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

    public ?string $siteName = null;

    public bool $editingSiteName = false;

    public function mount(Order $order): void
    {
        $customer = AccountContext::customer();
        abort_unless($customer !== null && (int) $order->customer_id === (int) $customer->id, 404);

        $this->order = $order->load(['lines', 'shippingAddress', 'billingAddress']);
        $this->siteName = $order->pko_site_name;
    }

    public function saveSiteName(): void
    {
        // Re-guard on every write: mount() guard does not run on subsequent Livewire requests.
        $customer = AccountContext::customer();
        abort_unless(
            $customer !== null && (int) $this->order->customer_id === (int) $customer->id,
            403,
        );

        $this->validate([
            'siteName' => ['nullable', 'string', 'max:255'],
        ]);

        $cleaned = filled($this->siteName) ? strip_tags($this->siteName) : null;
        $this->siteName = $cleaned;

        $this->order->forceFill(['pko_site_name' => $cleaned])->save();
        $this->order->refresh();

        $this->editingSiteName = false;
    }

    public function cancelEditSiteName(): void
    {
        $this->siteName = $this->order->pko_site_name;
        $this->editingSiteName = false;
    }

    #[Layout('account::layouts.account')]
    public function render(): View
    {
        return view('account::livewire.order-detail-page');
    }
}

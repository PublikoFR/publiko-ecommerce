<?php

declare(strict_types=1);

namespace Mde\Account\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Lunar\Models\Order;
use Mde\Account\Support\AccountContext;

class OrdersPage extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function getOrdersProperty(): LengthAwarePaginator
    {
        $customer = AccountContext::customer();

        $query = Order::query()
            ->when($customer, fn ($q) => $q->where('customer_id', $customer->id))
            ->unless($customer, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('placed_at')
            ->orderByDesc('id');

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return $query->paginate(10);
    }

    #[Layout('account::layouts.account')]
    public function render(): View
    {
        return view('account::livewire.orders-page');
    }
}

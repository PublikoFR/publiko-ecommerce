<?php

declare(strict_types=1);

namespace Pko\Account\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Pko\Account\Contracts\CustomerInvoices;

final class EmptyCustomerInvoices implements CustomerInvoices
{
    public function forCustomer(int $customerId): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 10);
    }
}

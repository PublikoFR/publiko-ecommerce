<?php

declare(strict_types=1);

namespace Pko\Account\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerInvoices
{
    /** Rows contain date, number, order_reference, order_url, type, total, download_url and view_url. No provider URL. */
    public function forCustomer(int $customerId): LengthAwarePaginator;
}

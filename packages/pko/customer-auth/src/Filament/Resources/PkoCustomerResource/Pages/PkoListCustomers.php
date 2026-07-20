<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages;

use Lunar\Admin\Filament\Resources\CustomerResource\Pages\ListCustomers;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource;

class PkoListCustomers extends ListCustomers
{
    protected static string $resource = PkoCustomerResource::class;
}

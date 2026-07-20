<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages;

use Lunar\Admin\Filament\Resources\CustomerResource\Pages\EditCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource;

class PkoEditCustomer extends EditCustomer
{
    protected static string $resource = PkoCustomerResource::class;
}

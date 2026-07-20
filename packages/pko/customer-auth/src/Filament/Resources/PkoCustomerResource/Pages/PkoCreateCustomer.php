<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\Pages;

use Lunar\Admin\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource;

class PkoCreateCustomer extends CreateCustomer
{
    protected static string $resource = PkoCustomerResource::class;
}

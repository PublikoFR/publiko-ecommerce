<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Resources\ShippingSurchargeResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Pko\ShippingCommon\Filament\Resources\ShippingSurchargeResource;

class CreateShippingSurcharge extends BaseCreateRecord
{
    protected static string $resource = ShippingSurchargeResource::class;
}

<?php

use App\Providers\AppServiceProvider;
use Mde\ShippingChronopost\ShippingChronopostServiceProvider;
use Mde\ShippingColissimo\ShippingColissimoServiceProvider;
use Mde\ShippingCommon\ShippingCommonServiceProvider;

return [
    AppServiceProvider::class,
    ShippingCommonServiceProvider::class,
    ShippingChronopostServiceProvider::class,
    ShippingColissimoServiceProvider::class,
];

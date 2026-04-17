<?php

use App\Providers\AppServiceProvider;
use Mde\AiImporter\AiImporterServiceProvider;
use Mde\CatalogFeatures\CatalogFeaturesServiceProvider;
use Mde\Loyalty\LoyaltyServiceProvider;
use Mde\ShippingChronopost\ShippingChronopostServiceProvider;
use Mde\ShippingColissimo\ShippingColissimoServiceProvider;
use Mde\ShippingCommon\ShippingCommonServiceProvider;

return [
    AppServiceProvider::class,
    CatalogFeaturesServiceProvider::class,
    LoyaltyServiceProvider::class,
    ShippingCommonServiceProvider::class,
    ShippingChronopostServiceProvider::class,
    ShippingColissimoServiceProvider::class,
    AiImporterServiceProvider::class,
];

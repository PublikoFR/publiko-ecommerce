<?php

use App\Providers\AppServiceProvider;
use Mde\Account\AccountServiceProvider;
use Mde\AiImporter\AiImporterServiceProvider;
use Mde\CatalogFeatures\CatalogFeaturesServiceProvider;
use Mde\CustomerAuth\CustomerAuthServiceProvider;
use Mde\Loyalty\LoyaltyServiceProvider;
use Mde\PurchaseLists\PurchaseListsServiceProvider;
use Mde\QuickOrder\QuickOrderServiceProvider;
use Mde\ShippingChronopost\ShippingChronopostServiceProvider;
use Mde\ShippingColissimo\ShippingColissimoServiceProvider;
use Mde\ShippingCommon\ShippingCommonServiceProvider;
use Mde\Storefront\StorefrontServiceProvider;
use Mde\StorefrontCms\StorefrontCmsServiceProvider;

return [
    AppServiceProvider::class,
    CatalogFeaturesServiceProvider::class,
    LoyaltyServiceProvider::class,
    ShippingCommonServiceProvider::class,
    ShippingChronopostServiceProvider::class,
    ShippingColissimoServiceProvider::class,
    AiImporterServiceProvider::class,
    StorefrontServiceProvider::class,
    CustomerAuthServiceProvider::class,
    AccountServiceProvider::class,
    PurchaseListsServiceProvider::class,
    QuickOrderServiceProvider::class,
    StorefrontCmsServiceProvider::class,
];

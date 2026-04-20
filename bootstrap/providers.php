<?php

use App\Providers\AppServiceProvider;
use Pko\Account\AccountServiceProvider;
use Pko\AiCore\AiCoreServiceProvider;
use Pko\AiFilament\AiFilamentServiceProvider;
use Pko\AiImporter\AiImporterServiceProvider;
use Pko\CatalogFeatures\CatalogFeaturesServiceProvider;
use Pko\CustomerAuth\CustomerAuthServiceProvider;
use Pko\Loyalty\LoyaltyServiceProvider;
use Pko\PageBuilder\PageBuilderServiceProvider;
use Pko\ProductVideos\ProductVideosServiceProvider;
use Pko\PurchaseLists\PurchaseListsServiceProvider;
use Pko\QuickOrder\QuickOrderServiceProvider;
use Pko\ShippingChronopost\ShippingChronopostServiceProvider;
use Pko\ShippingColissimo\ShippingColissimoServiceProvider;
use Pko\ShippingCommon\ShippingCommonServiceProvider;
use Pko\Storefront\StorefrontServiceProvider;
use Pko\StorefrontCms\StorefrontCmsServiceProvider;
use Pko\StoreLocator\StoreLocatorServiceProvider;

return [
    AppServiceProvider::class,
    CatalogFeaturesServiceProvider::class,
    LoyaltyServiceProvider::class,
    ShippingCommonServiceProvider::class,
    ShippingChronopostServiceProvider::class,
    ShippingColissimoServiceProvider::class,
    AiCoreServiceProvider::class,
    AiFilamentServiceProvider::class,
    AiImporterServiceProvider::class,
    ProductVideosServiceProvider::class,
    PageBuilderServiceProvider::class,
    StorefrontServiceProvider::class,
    CustomerAuthServiceProvider::class,
    AccountServiceProvider::class,
    PurchaseListsServiceProvider::class,
    QuickOrderServiceProvider::class,
    StorefrontCmsServiceProvider::class,
    StoreLocatorServiceProvider::class,
];

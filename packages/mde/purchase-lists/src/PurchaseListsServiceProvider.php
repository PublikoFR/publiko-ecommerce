<?php

declare(strict_types=1);

namespace Mde\PurchaseLists;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Lunar\Models\Customer;
use Mde\PurchaseLists\Livewire\PurchaseListPage;
use Mde\PurchaseLists\Livewire\PurchaseListPicker;
use Mde\PurchaseLists\Livewire\PurchaseListsPage;
use Mde\PurchaseLists\Models\PurchaseList;

class PurchaseListsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'purchase-lists');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Livewire::component('purchase-lists.index', PurchaseListsPage::class);
        Livewire::component('purchase-lists.detail', PurchaseListPage::class);
        Livewire::component('purchase-lists.picker', PurchaseListPicker::class);

        Customer::resolveRelationUsing(
            'purchaseLists',
            fn (Customer $customer) => $customer->hasMany(PurchaseList::class, 'customer_id'),
        );
    }
}

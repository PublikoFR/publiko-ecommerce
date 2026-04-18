<?php

declare(strict_types=1);

namespace Pko\PurchaseLists;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Lunar\Models\Customer;
use Pko\PurchaseLists\Livewire\PurchaseListPage;
use Pko\PurchaseLists\Livewire\PurchaseListPicker;
use Pko\PurchaseLists\Livewire\PurchaseListsPage;
use Pko\PurchaseLists\Models\PurchaseList;

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

<?php

declare(strict_types=1);

namespace Mde\QuickOrder;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Mde\QuickOrder\Livewire\QuickOrderPage;

class QuickOrderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'quick-order');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Livewire::component('quick-order.page', QuickOrderPage::class);
    }
}

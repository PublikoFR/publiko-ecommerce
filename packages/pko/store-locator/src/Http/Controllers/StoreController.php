<?php

declare(strict_types=1);

namespace Pko\StoreLocator\Http\Controllers;

use Illuminate\Contracts\View\View;
use Pko\StoreLocator\Models\Store;

class StoreController
{
    public function index(): View
    {
        $stores = Store::active()->get();

        return view('store-locator::index', ['stores' => $stores]);
    }

    public function show(string $slug): View
    {
        $store = Store::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return view('store-locator::show', ['store' => $store]);
    }
}

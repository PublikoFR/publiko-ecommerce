<?php

declare(strict_types=1);

use App\Livewire\CartPage;
use App\Livewire\CheckoutPage;
use App\Livewire\CheckoutSuccessPage;
use App\Livewire\CollectionPage;
use App\Livewire\CollectionsIndexPage;
use App\Livewire\Home;
use App\Livewire\ProductPage;
use App\Livewire\SearchPage;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');
Route::get('/recherche', SearchPage::class)->name('search.view');
Route::get('/search', SearchPage::class);
Route::get('/collections', CollectionsIndexPage::class)->name('collections.index');
Route::get('/collections/{slug}', CollectionPage::class)->name('collection.view');
Route::get('/produits/{slug}', ProductPage::class)->name('product.view');
Route::get('/products/{slug}', ProductPage::class);

Route::middleware(['pro.customer'])->group(function () {
    Route::get('/panier', CartPage::class)->name('cart.view');
    Route::get('/checkout', CheckoutPage::class)->name('checkout.view');
    Route::get('/checkout/success', CheckoutSuccessPage::class)->name('checkout-success.view');
});

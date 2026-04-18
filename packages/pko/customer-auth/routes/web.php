<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Pko\CustomerAuth\Livewire\ForgotPasswordPage;
use Pko\CustomerAuth\Livewire\LoginPage;
use Pko\CustomerAuth\Livewire\RegisterPage;
use Pko\CustomerAuth\Livewire\ResetPasswordPage;

Route::middleware(['web', 'redirect.if.pro'])->group(function () {
    Route::get('/connexion', LoginPage::class)->name('login');
    Route::get('/inscription', RegisterPage::class)->name('register');
    Route::get('/mot-de-passe-oublie', ForgotPasswordPage::class)->name('password.request');
    Route::get('/reinitialisation/{token}', ResetPasswordPage::class)->name('password.reset');
});

Route::middleware('web')->post('/deconnexion', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');

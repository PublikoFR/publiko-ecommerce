<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Mde\CustomerAuth\Livewire\ForgotPasswordPage;
use Mde\CustomerAuth\Livewire\LoginPage;
use Mde\CustomerAuth\Livewire\RegisterPage;
use Mde\CustomerAuth\Livewire\ResetPasswordPage;

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

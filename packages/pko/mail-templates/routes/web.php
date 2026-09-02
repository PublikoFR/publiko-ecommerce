<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pko\MailTemplates\Http\Controllers\MailPreviewController;

// Chargées uniquement en local/testing (cf. MailTemplatesServiceProvider).
Route::middleware('web')->group(function () {
    Route::get('/_mail', [MailPreviewController::class, 'index'])->name('pko.mail.preview.index');
    Route::get('/_mail/{key}', [MailPreviewController::class, 'show'])->name('pko.mail.preview.show');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mde\StorefrontCms\Http\Controllers\NewsletterController;
use Mde\StorefrontCms\Http\Controllers\PageController;
use Mde\StorefrontCms\Http\Controllers\PostController;

Route::middleware('web')->group(function () {
    Route::get('/actualites', [PostController::class, 'index'])->name('posts.index');
    Route::get('/actualites/{slug}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');
    Route::post('/newsletter', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
});

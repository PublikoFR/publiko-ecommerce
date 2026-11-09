<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pko\QuoteRequests\Http\Controllers\DownloadQuoteRequestFileController;
use Pko\QuoteRequests\Livewire\QuoteRequestPage;
use Pko\QuoteRequests\Support\QuoteRequestUrl;

// Page publique : ouverte à tous (prospect non connecté compris). Route
// explicite, prioritaire sur le catch-all CMS (Route::fallback).
Route::middleware('web')
    ->get(QuoteRequestUrl::PATH, QuoteRequestPage::class)
    ->name('quote-request');

// Pièces jointes : disque privé, téléchargement réservé au staff autorisé à
// voir la demande (policy Shield `view_quote::request`).
Route::middleware(['web', 'auth:staff'])
    ->get('/admin/quote-requests/files/{file}/download', DownloadQuoteRequestFileController::class)
    ->name('quote-requests.files.download');

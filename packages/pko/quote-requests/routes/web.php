<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pko\QuoteRequests\Http\Controllers\DownloadQuoteRequestFileController;
use Pko\QuoteRequests\Http\Controllers\PreviewQuoteRequestFileController;
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

// Aperçu inline des photos (miniatures + lightbox) : mêmes droits que le
// téléchargement, formats raster détectés uniquement.
Route::middleware(['web', 'auth:staff'])
    ->get('/admin/quote-requests/files/{file}/preview', PreviewQuoteRequestFileController::class)
    ->name('quote-requests.files.preview');

// La page admin « Contacts » s'appelait « Demandes de devis » (slug
// quote-requests) : les liens des e-mails équipe déjà envoyés restent valides.
Route::middleware('web')->group(function (): void {
    Route::redirect('/admin/quote-requests', '/admin/contacts', 301);
    Route::get('/admin/quote-requests/{id}', fn (string $id) => redirect('/admin/contacts/'.$id, 301))
        ->whereNumber('id');
});

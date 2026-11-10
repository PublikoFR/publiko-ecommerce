<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Pko\QuoteRequests\Models\QuoteRequestFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aperçu inline d'une photo jointe (miniatures + lightbox de la fiche admin).
 *
 * Réservé aux formats raster dont le type a été DÉTECTÉ à l'envoi par les
 * magic bytes (jpg, png, webp) : jamais de SVG ni de HTML rendu inline. Les
 * en-têtes interdisent le reniflage de type et tout contenu actif.
 */
final class PreviewQuoteRequestFileController
{
    public function __invoke(Request $request, QuoteRequestFile $file): StreamedResponse
    {
        abort_unless($file->isPreviewable(), 404);

        DownloadQuoteRequestFileController::authorizeStaff($request, $file);

        return Storage::disk($file->disk)->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], 'inline');
    }
}

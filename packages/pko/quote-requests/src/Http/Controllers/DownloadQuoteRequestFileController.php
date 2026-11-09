<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Pko\QuoteRequests\Models\QuoteRequestFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadQuoteRequestFileController
{
    public function __invoke(Request $request, QuoteRequestFile $file): StreamedResponse
    {
        $staff = $request->user('staff');
        abort_if($staff === null, 403);

        // Même règle que la fiche admin (Filament) : la policy du modèle si
        // elle est enregistrée, sinon tout membre du staff connecté. Le
        // téléchargement n'ouvre jamais plus que la page qui affiche le lien.
        $gate = Gate::forUser($staff);
        $quoteRequest = $file->quoteRequest;
        abort_unless($gate->getPolicyFor($quoteRequest) === null || $gate->allows('view', $quoteRequest), 403);

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404);

        // Toujours en pièce jointe : un fichier envoyé par un tiers n'est jamais
        // rendu dans l'onglet de l'admin (pas d'exécution de contenu actif).
        return $disk->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

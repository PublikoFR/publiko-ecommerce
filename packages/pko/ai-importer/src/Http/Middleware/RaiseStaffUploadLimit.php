<?php

declare(strict_types=1);

namespace Pko\AiImporter\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relève, pour le personnel connecté au back-office seulement, la taille
 * maximale d'un envoi de fichier temporaire Livewire.
 *
 * Par défaut Livewire refuse tout fichier de plus de 12 Mo AVANT que le champ
 * du formulaire n'applique sa propre limite : un tarif fournisseur de 20 Mo
 * était rejeté à l'envoi alors que le champ en annonce 100. La limite Livewire
 * est globale (elle protège aussi les envois publics du storefront) : on ne la
 * relève donc que pour une session du guard `staff`, à la valeur du champ
 * d'import (`ai-importer.upload.max_size_kb`).
 *
 * Branché sur la route d'envoi Livewire via `livewire.temporary_file_upload.middleware`.
 */
final class RaiseStaffUploadLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('ai-importer.upload.max_size_kb', 0);

        if ($limit > 12288 && auth('staff')->check()) {
            config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.$limit]]);
        }

        return $next($request);
    }
}

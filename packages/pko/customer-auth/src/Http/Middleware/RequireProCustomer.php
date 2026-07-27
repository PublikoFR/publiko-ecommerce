<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Pko\CustomerAuth\Support\ProAccess;
use Symfony\Component\HttpFoundation\Response;

class RequireProCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $reason = ProAccess::denialReason($request->user());

        if ($reason === null) {
            return $next($request);
        }

        // Un utilisateur authentifié mais sans accès est en « semi-connexion » :
        // le header affiche son nom alors qu'aucune page ne lui est ouverte. On
        // le déconnecte. Les deux cas où c'est volontaire — impersonation admin
        // et auto-login juste après l'inscription — sont déjà exclus en amont :
        // ProAccess::denialReason() y retourne null, donc on ne descend jamais ici.
        // `logout()` seul, jamais `invalidate()`/`regenerateToken()` : périmer le
        // token CSRF ferait tomber en 419 (« This page has expired ») les
        // composants Livewire des pages déjà ouvertes. Retirer l'authentification
        // suffit à supprimer la semi-connexion.
        if ($request->user() !== null) {
            Auth::guard('web')->logout();
        }

        return $this->redirect($request, $reason);
    }

    private function redirect(Request $request, string $message): Response
    {
        return redirect('/connexion?intended='.urlencode($request->fullUrl()))
            ->with('status', $message);
    }
}

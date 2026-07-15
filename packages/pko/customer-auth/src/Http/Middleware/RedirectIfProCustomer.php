<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pko\CustomerAuth\Support\ProAccess;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfProCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        // On ne redirige vers l'espace pro QUE si l'utilisateur est un pro actif.
        // Un utilisateur authentifié mais non-actif (SIRET en attente, hors groupe)
        // reste sur /connexion — sinon boucle /compte ↔ /connexion.
        if (ProAccess::isActivePro($request->user())) {
            return redirect('/compte');
        }

        return $next($request);
    }
}

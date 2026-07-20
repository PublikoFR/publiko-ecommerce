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
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Pro actif → espace pro.
        if (ProAccess::isActivePro($user)) {
            return redirect('/compte');
        }

        // Authentifié mais compte non-actif (SIRET pending, hors groupe, sans
        // customer) → on laisse la page de connexion s'afficher. Surtout PAS de
        // redirect vers /compte (qui renverrait vers /connexion → boucle), ni
        // vers /, sinon l'utilisateur ne peut jamais atteindre /connexion.
        return $next($request);
    }
}

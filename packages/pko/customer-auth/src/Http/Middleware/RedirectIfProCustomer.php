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

        // Authentifié mais compte en attente / non rattaché → accueil avec message.
        // Pas de redirect vers /compte (qui renverrait vers /connexion → boucle).
        return redirect('/')->with('status', 'Votre compte est en cours de validation. Vous serez notifié par e-mail dès activation.');
    }
}

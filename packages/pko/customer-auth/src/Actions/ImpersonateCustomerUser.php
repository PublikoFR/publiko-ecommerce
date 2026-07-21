<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Pko\CustomerAuth\Support\ProAccess;

/**
 * Connecte un utilisateur du front (guard `web`) depuis le back-office.
 *
 * Subtilité : pendant une requête Filament, le panel Lunar force le guard par
 * défaut sur `staff` (`Auth::shouldUse('staff')`). Or les listeners branchés sur
 * l'événement `Login` — notamment `Lunar\Listeners\CartSessionAuthListener` —
 * résolvent l'utilisateur courant via le guard **par défaut**, pas via celui qui
 * a émis l'événement. Sans bascule, `CartSession::current()` récupère le Staff
 * connecté à l'admin et appelle `Staff::carts()`, qui n'existe pas
 * (BadMethodCallException, HTTP 500).
 *
 * On bascule donc le guard par défaut sur `web` le temps du login, puis on
 * restaure celui d'origine pour ne pas perturber la fin de la requête admin.
 */
class ImpersonateCustomerUser
{
    public function __invoke(Authenticatable $user, string $guard = 'web'): void
    {
        $previousGuard = Auth::getDefaultDriver();
        $impersonatorId = Auth::guard('staff')->id();

        Auth::shouldUse($guard);

        try {
            Auth::guard($guard)->login($user);
        } finally {
            Auth::shouldUse($previousGuard);
        }

        // Marque la session comme « ouverte par un admin » : ProAccess laisse
        // alors passer le gate pro même si le compte client est en attente de
        // validation ou son e-mail non confirmé (support back-office). Posé
        // après le login, car SessionGuard::login() régénère l'id de session.
        if ($impersonatorId !== null) {
            Session::put(ProAccess::IMPERSONATOR_SESSION_KEY, $impersonatorId);
        }
    }
}

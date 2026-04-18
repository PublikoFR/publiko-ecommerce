<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireProCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->redirect($request, 'Connectez-vous pour accéder à cette page.');
        }

        $customer = method_exists($user, 'customers') ? $user->customers()->first() : null;

        if (! $customer) {
            return $this->redirect($request, 'Votre compte n\'est pas encore rattaché à une société pro.');
        }

        $status = $customer->getAttribute('sirene_status');
        if ($status !== null && $status !== 'active') {
            return $this->redirect($request, 'Votre compte est en cours de validation. Vous serez notifié par e-mail.');
        }

        $required = (string) config('customer-auth.default_customer_group_handle', 'installateurs');
        $hasGroup = $customer->customerGroups()->where('handle', $required)->exists();

        if (! $hasGroup) {
            return $this->redirect($request, 'Accès réservé aux comptes professionnels.');
        }

        return $next($request);
    }

    private function redirect(Request $request, string $message): Response
    {
        return redirect('/connexion?intended='.urlencode($request->fullUrl()))
            ->with('status', $message);
    }
}

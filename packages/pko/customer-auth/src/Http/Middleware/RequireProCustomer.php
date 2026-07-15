<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pko\CustomerAuth\Support\ProAccess;
use Symfony\Component\HttpFoundation\Response;

class RequireProCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $reason = ProAccess::denialReason($request->user());

        if ($reason !== null) {
            return $this->redirect($request, $reason);
        }

        return $next($request);
    }

    private function redirect(Request $request, string $message): Response
    {
        return redirect('/connexion?intended='.urlencode($request->fullUrl()))
            ->with('status', $message);
    }
}

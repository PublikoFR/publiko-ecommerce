<?php

declare(strict_types=1);

namespace Mde\CustomerAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfProCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return redirect('/compte');
        }

        return $next($request);
    }
}

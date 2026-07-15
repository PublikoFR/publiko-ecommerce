<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pko\StorefrontCms\Models\Setting;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'accès au storefront pendant la maintenance.
 * Les membres du staff (lunar_staff) passent toujours, même en maintenance.
 */
class CheckStorefrontMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::get('storefront.maintenance', false)) {
            return $next($request);
        }

        // Les membres du staff Lunar (guard « staff », utilisé par Filament)
        // passent toujours, même en maintenance.
        if (auth('staff')->check()) {
            return $next($request);
        }

        return response()->view('storefront-cms::maintenance', [], 503);
    }
}

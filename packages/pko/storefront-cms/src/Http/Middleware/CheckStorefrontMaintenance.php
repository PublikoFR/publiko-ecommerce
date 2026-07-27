<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pko\StorefrontCms\Models\Setting;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'accès au storefront pendant la maintenance.
 *
 * Appliqué globalement au groupe `web` (cf. bootstrap/app.php) → il couvre
 * TOUTES les pages front (storefront, compte, auth, packages…), pas seulement
 * les routes de routes/web.php. Le panel admin Filament a sa propre pile de
 * middleware (hors groupe web) et n'est donc jamais impacté.
 *
 * Les membres du staff (lunar_staff) passent toujours, même en maintenance —
 * ils voient le storefront normal, coiffé d'un bandeau d'alerte rouge
 * (cf. x-storefront.maintenance-banner).
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

        // L'endpoint Livewire partagé (/livewire/update) transite par le groupe
        // web : c'est aussi lui qui porte la soumission du formulaire de
        // connexion admin Filament, alors que le staff n'est pas ENCORE
        // authentifié. Le bloquer enfermerait l'admin dehors pendant la
        // maintenance. Aucun risque de fuite : les pages front étant coupées au
        // GET pour les non-staff, aucun composant front n'est monté et donc
        // aucun snapshot valide ne peut être rejoué ici.
        if ($request->is('livewire/*')) {
            return $next($request);
        }

        return response()->view('storefront-cms::maintenance', [], 503);
    }
}

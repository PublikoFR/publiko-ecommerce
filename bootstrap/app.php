<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Pko\StorefrontCms\Http\Middleware\CheckStorefrontMaintenance;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'storefront.maintenance' => CheckStorefrontMaintenance::class,
        ]);

        // La déconnexion est idempotente et non destructive : on l'exempte de la
        // validation CSRF pour qu'un token périmé (page SPA wire:navigate ouverte
        // longtemps, restaurée depuis le cache back/forward) ne renvoie pas un 419
        // qui laisserait l'utilisateur connecté sans pouvoir se déconnecter.
        $middleware->validateCsrfTokens(except: [
            'deconnexion',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

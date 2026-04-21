<?php

declare(strict_types=1);

use Pko\Secrets\SecretStore;

if (! function_exists('secret')) {
    /**
     * Resolve a credential via SecretStore (env or DB, per module).
     * Expected format: "module.key" (e.g. "stripe.secret").
     */
    function secret(string $path, ?string $default = null): ?string
    {
        if (! str_contains($path, '.')) {
            return $default;
        }

        [$module, $key] = explode('.', $path, 2);

        return app(SecretStore::class)->get($module, $key, $default);
    }
}

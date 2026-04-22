<?php

declare(strict_types=1);

namespace Pko\Secrets\Providers;

use Pko\Secrets\Contracts\SecretProvider;
use Pko\Secrets\Registry;
use RuntimeException;

class EnvProvider implements SecretProvider
{
    public function __construct(protected Registry $registry) {}

    public function get(string $module, string $key): ?string
    {
        $envKey = $this->registry->envKey($module, $key);
        if ($envKey === null) {
            return null;
        }

        $value = env($envKey);

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function set(string $module, string $key, ?string $value): void
    {
        throw new RuntimeException("Cannot write secret for module [{$module}] in env mode. Switch to db mode first.");
    }

    public function has(string $module, string $key): bool
    {
        return $this->get($module, $key) !== null;
    }
}

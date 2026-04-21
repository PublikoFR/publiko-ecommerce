<?php

declare(strict_types=1);

namespace Pko\Secrets;

use Pko\Secrets\Providers\DatabaseProvider;
use Pko\Secrets\Providers\EnvProvider;
use Pko\StorefrontCms\Models\Setting;
use RuntimeException;

class SecretStore
{
    public function __construct(
        protected Registry $registry,
        protected EnvProvider $env,
        protected DatabaseProvider $db,
    ) {}

    public function register(string $module, array $keys, string $defaultSource = 'env', ?string $label = null, array $configMap = []): void
    {
        $this->registry->register($module, $keys, $defaultSource, $label, $configMap);
    }

    public function registry(): Registry
    {
        return $this->registry;
    }

    public function get(string $module, string $key, ?string $default = null): ?string
    {
        $value = $this->resolveProvider($module)->get($module, $key);

        return $value ?? $default;
    }

    public function set(string $module, string $key, ?string $value): void
    {
        if ($this->source($module) !== 'db') {
            throw new RuntimeException("Cannot write secret for module [{$module}]: source is env. Call useDatabase() first.");
        }

        $this->db->set($module, $key, $value);
    }

    public function has(string $module, string $key): bool
    {
        return $this->resolveProvider($module)->has($module, $key);
    }

    public function source(string $module): string
    {
        if (! $this->registry->hasModule($module)) {
            return 'env';
        }

        $stored = Setting::get($this->sourceSettingKey($module));

        if (is_string($stored) && in_array($stored, ['env', 'db'], true)) {
            return $stored;
        }

        return $this->registry->defaultSource($module);
    }

    public function useDatabase(string $module): void
    {
        Setting::set($this->sourceSettingKey($module), 'db');
        DatabaseProvider::flushCache();
    }

    public function useEnv(string $module): void
    {
        Setting::set($this->sourceSettingKey($module), 'env');
        DatabaseProvider::flushCache();
    }

    public function flushCache(): void
    {
        DatabaseProvider::flushCache();
    }

    protected function resolveProvider(string $module): EnvProvider|DatabaseProvider
    {
        return $this->source($module) === 'db' ? $this->db : $this->env;
    }

    protected function sourceSettingKey(string $module): string
    {
        return "secrets.{$module}.source";
    }
}

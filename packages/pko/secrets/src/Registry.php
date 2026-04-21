<?php

declare(strict_types=1);

namespace Pko\Secrets;

class Registry
{
    /**
     * @var array<string, array{keys: array<string, string>, config_map: array<string, string>, default_source: string, label: string|null}>
     */
    protected array $modules = [];

    /**
     * @param  array<string, string>  $keys  map of logical key → env var name
     * @param  array<string, string>  $configMap  optional map of logical key → dotted config path to backfill
     *                                            when the module is in DB mode (e.g. 'secret' => 'services.stripe.key')
     */
    public function register(string $module, array $keys, string $defaultSource = 'env', ?string $label = null, array $configMap = []): void
    {
        $this->modules[$module] = [
            'keys' => $keys,
            'config_map' => $configMap,
            'default_source' => $defaultSource === 'db' ? 'db' : 'env',
            'label' => $label,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function configMap(string $module): array
    {
        return $this->modules[$module]['config_map'] ?? [];
    }

    public function hasModule(string $module): bool
    {
        return isset($this->modules[$module]);
    }

    /**
     * @return array<string, string>
     */
    public function keys(string $module): array
    {
        return $this->modules[$module]['keys'] ?? [];
    }

    public function envKey(string $module, string $key): ?string
    {
        return $this->modules[$module]['keys'][$key] ?? null;
    }

    public function defaultSource(string $module): string
    {
        return $this->modules[$module]['default_source'] ?? 'env';
    }

    public function label(string $module): ?string
    {
        return $this->modules[$module]['label'] ?? null;
    }

    /**
     * @return array<string>
     */
    public function modules(): array
    {
        return array_keys($this->modules);
    }
}

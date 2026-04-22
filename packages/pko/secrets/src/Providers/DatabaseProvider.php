<?php

declare(strict_types=1);

namespace Pko\Secrets\Providers;

use Illuminate\Support\Facades\Cache;
use Pko\Secrets\Contracts\SecretProvider;
use Pko\Secrets\Models\Secret;

class DatabaseProvider implements SecretProvider
{
    protected const CACHE_KEY = 'pko.secrets.db.v1';

    protected const CACHE_TTL = 3600;

    public function get(string $module, string $key): ?string
    {
        $all = $this->loadAll();
        $value = $all["{$module}.{$key}"] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function set(string $module, string $key, ?string $value): void
    {
        Secret::updateOrCreate(
            ['module' => $module, 'key' => $key],
            ['value' => $value]
        );
        Cache::forget(self::CACHE_KEY);
    }

    public function has(string $module, string $key): bool
    {
        return $this->get($module, $key) !== null;
    }

    /**
     * @return array<string, string|null>
     */
    protected function loadAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $out = [];
            foreach (Secret::query()->get(['module', 'key', 'value']) as $row) {
                $out["{$row->module}.{$row->key}"] = $row->value;
            }

            return $out;
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

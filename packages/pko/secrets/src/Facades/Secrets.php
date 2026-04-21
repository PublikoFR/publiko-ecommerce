<?php

declare(strict_types=1);

namespace Pko\Secrets\Facades;

use Illuminate\Support\Facades\Facade;
use Pko\Secrets\SecretStore;

/**
 * @method static void register(string $module, array $keys, string $defaultSource = 'env', ?string $label = null)
 * @method static \Pko\Secrets\Registry registry()
 * @method static ?string get(string $module, string $key, ?string $default = null)
 * @method static void set(string $module, string $key, ?string $value)
 * @method static bool has(string $module, string $key)
 * @method static string source(string $module)
 * @method static void useDatabase(string $module)
 * @method static void useEnv(string $module)
 * @method static void flushCache()
 */
class Secrets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SecretStore::class;
    }
}

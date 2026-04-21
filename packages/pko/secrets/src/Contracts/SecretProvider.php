<?php

declare(strict_types=1);

namespace Pko\Secrets\Contracts;

interface SecretProvider
{
    public function get(string $module, string $key): ?string;

    public function set(string $module, string $key, ?string $value): void;

    public function has(string $module, string $key): bool;
}

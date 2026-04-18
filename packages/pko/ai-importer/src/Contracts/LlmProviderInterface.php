<?php

declare(strict_types=1);

namespace Pko\AiImporter\Contracts;

interface LlmProviderInterface
{
    /**
     * Execute a prompt against the configured provider/model.
     *
     * @param  array<string, mixed>  $inputs  named inputs injected in the prompt
     * @param  array<string, mixed>  $options  provider-specific overrides
     */
    public function transform(string $prompt, array $inputs = [], array $options = []): string;

    /**
     * Lightweight health check (credentials + reachability). Throws on failure.
     */
    public function testConnection(): bool;
}

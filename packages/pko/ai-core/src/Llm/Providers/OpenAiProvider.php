<?php

declare(strict_types=1);

namespace Pko\AiCore\Llm\Providers;

use Illuminate\Support\Facades\Http;
use Pko\AiCore\Contracts\LlmProviderInterface;

final class OpenAiProvider implements LlmProviderInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly array $options = [],
    ) {}

    public function transform(string $prompt, array $inputs = [], array $options = []): string
    {
        $messages = [];
        // Contexte global (option `system`) injecté en message système. OpenAI gère
        // le caching automatiquement côté serveur ; `cache_system` est sans effet ici.
        if (! empty($options['system'])) {
            $messages[] = ['role' => 'system', 'content' => (string) $options['system']];
        }
        $messages[] = ['role' => 'user', 'content' => $this->assembleMessage($prompt, $inputs)];

        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? (int) (($this->options['max_tokens'] ?? null) ?? 1024),
        ];

        $response = Http::timeout((int) config('ai-core.llm.http_timeout', 60))
            ->withToken($this->apiKey)
            ->retry(
                (int) config('ai-core.llm.retries', 3),
                fn (int $attempt): int => config('ai-core.llm.retry_backoff_ms', [2000, 5000, 10000])[$attempt - 1] ?? 10000,
                throw: false,
            )
            ->post(self::ENDPOINT, $body);

        if ($response->failed()) {
            throw new \RuntimeException("OpenAI API error ({$response->status()}): {$response->body()}");
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }

    public function testConnection(): bool
    {
        $this->transform('Réponds juste "ok".');

        return true;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function assembleMessage(string $prompt, array $inputs): string
    {
        if ($inputs === []) {
            return $prompt;
        }

        return $prompt."\n\nDonnées :\n".json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

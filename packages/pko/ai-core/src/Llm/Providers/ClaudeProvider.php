<?php

declare(strict_types=1);

namespace Pko\AiCore\Llm\Providers;

use Illuminate\Support\Facades\Http;
use Pko\AiCore\Contracts\LlmProviderInterface;

final class ClaudeProvider implements LlmProviderInterface
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly array $options = [],
    ) {}

    public function transform(string $prompt, array $inputs = [], array $options = []): string
    {
        $body = [
            'model' => $this->model,
            'max_tokens' => $options['max_tokens'] ?? (int) (($this->options['max_tokens'] ?? null) ?? 1024),
            'messages' => [
                ['role' => 'user', 'content' => $this->assembleMessage($prompt, $inputs)],
            ],
        ];

        $response = Http::timeout((int) config('ai-core.llm.http_timeout', 60))
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ])
            ->retry(
                (int) config('ai-core.llm.retries', 3),
                fn (int $attempt): int => config('ai-core.llm.retry_backoff_ms', [2000, 5000, 10000])[$attempt - 1] ?? 10000,
                fn (\Throwable $e, $req) => ! $this->isCriticalHttpError($e),
                throw: false,
            )
            ->post(self::ENDPOINT, $body);

        if ($response->failed()) {
            throw new \RuntimeException("Claude API error ({$response->status()}): {$response->body()}");
        }

        return (string) ($response->json('content.0.text') ?? '');
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

    private function isCriticalHttpError(\Throwable $e): bool
    {
        $code = method_exists($e, 'getCode') ? (int) $e->getCode() : 0;

        return in_array($code, (array) config('ai-core.llm.critical_status_codes', [401, 402, 403]), true);
    }
}

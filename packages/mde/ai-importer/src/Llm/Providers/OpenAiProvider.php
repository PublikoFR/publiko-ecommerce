<?php

declare(strict_types=1);

namespace Mde\AiImporter\Llm\Providers;

use Illuminate\Support\Facades\Http;
use Mde\AiImporter\Contracts\LlmProviderInterface;
use Mde\AiImporter\Models\LlmConfig;

final class OpenAiProvider implements LlmProviderInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(private readonly LlmConfig $config) {}

    public function transform(string $prompt, array $inputs = [], array $options = []): string
    {
        $body = [
            'model' => $this->config->model,
            'messages' => [
                ['role' => 'user', 'content' => $this->assembleMessage($prompt, $inputs)],
            ],
            'max_tokens' => $options['max_tokens'] ?? (int) (($this->config->options['max_tokens'] ?? null) ?? 1024),
        ];

        $response = Http::timeout((int) config('ai-importer.llm.http_timeout', 60))
            ->withToken($this->config->api_key)
            ->retry(
                (int) config('ai-importer.llm.retries', 3),
                fn (int $attempt): int => config('ai-importer.llm.retry_backoff_ms', [2000, 5000, 10000])[$attempt - 1] ?? 10000,
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

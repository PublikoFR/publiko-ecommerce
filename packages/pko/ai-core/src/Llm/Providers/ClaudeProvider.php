<?php

declare(strict_types=1);

namespace Pko\AiCore\Llm\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pko\AiCore\Contracts\LlmProviderInterface;
use Pko\AiCore\Llm\Concerns\RetriesTransientErrors;

/**
 * Anthropic Messages API (`POST /v1/messages`), en HTTP brut via le client
 * Laravel (pas de SDK PHP officiel dans le projet).
 *
 * Options reconnues (`LlmConfig::options`, surchargées par `$options` d'appel) :
 *  - `max_tokens`   plafond de génération (défaut `ai-core.llm.default_max_tokens`)
 *  - `effort`       `low|medium|high|xhigh|max` → `output_config.effort` (modèles
 *                   4.5+ ; à `low` sur un import ligne à ligne pour limiter le coût)
 *  - `temperature`  float (refusé par les modèles 5.x, à ne poser que sur 4.x)
 *  - `system`       contexte global ; `cache_system` l'émet avec `cache_control`
 *                   ephemeral (prompt caching, effectif au-delà du minimum de
 *                   tokens cacheables du modèle)
 *
 * Réponse : on prend le premier bloc `text` du tableau `content` — les modèles
 * à réflexion activée par défaut (Opus/Sonnet/Haiku 5.x) renvoient un bloc
 * `thinking` EN PREMIER, lire `content.0.text` rendrait une chaîne vide. Un
 * `stop_reason: refusal` lève une exception (la ligne passe en erreur, visible
 * dans les logs) ; `max_tokens` renvoie le texte tronqué avec un warning.
 */
final class ClaudeProvider implements LlmProviderInterface
{
    use RetriesTransientErrors;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    private const EFFORT_LEVELS = ['low', 'medium', 'high', 'xhigh', 'max'];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly array $options = [],
    ) {}

    public function transform(string $prompt, array $inputs = [], array $options = []): string
    {
        $body = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens($options),
            'messages' => [
                ['role' => 'user', 'content' => $this->assembleMessage($prompt, $inputs)],
            ],
        ];

        $effort = strtolower(trim((string) ($options['effort'] ?? $this->options['effort'] ?? '')));
        if (in_array($effort, self::EFFORT_LEVELS, true)) {
            $body['output_config'] = ['effort' => $effort];
        }

        $temperature = $options['temperature'] ?? $this->options['temperature'] ?? null;
        if (is_numeric($temperature)) {
            $body['temperature'] = (float) $temperature;
        }

        // Contexte global (option `system`). Si `cache_system`, on l'émet en bloc
        // texte avec `cache_control` ephemeral → prompt caching côté Anthropic.
        if (! empty($options['system'])) {
            $system = (string) $options['system'];
            $body['system'] = ! empty($options['cache_system'])
                ? [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]]
                : $system;
        }

        $request = Http::timeout((int) config('ai-core.llm.http_timeout', 60))
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ]);

        $response = $this->withTransientRetries($request)->post(self::ENDPOINT, $body);

        if ($response->failed()) {
            throw new \RuntimeException("Claude API error ({$response->status()}): {$response->body()}");
        }

        $stopReason = (string) ($response->json('stop_reason') ?? '');

        if ($stopReason === 'refusal') {
            $details = $response->json('stop_details');
            $why = is_array($details) ? trim(implode(' — ', array_filter([
                (string) ($details['category'] ?? ''),
                (string) ($details['explanation'] ?? ''),
            ]))) : '';

            throw new \RuntimeException('Claude a refusé la requête'.($why !== '' ? " ({$why})" : '').'.');
        }

        $text = $this->firstText($response->json('content'));

        if ($stopReason === 'max_tokens') {
            Log::warning('[ai-core] Réponse Claude tronquée (max_tokens atteint)', [
                'model' => $this->model,
                'max_tokens' => $body['max_tokens'],
                'output_tokens' => $response->json('usage.output_tokens'),
            ]);
        }

        return $text;
    }

    public function testConnection(): bool
    {
        $this->transform('Réponds juste "ok".', [], ['max_tokens' => 16]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function maxTokens(array $options): int
    {
        $value = $options['max_tokens'] ?? $this->options['max_tokens'] ?? null;

        return is_numeric($value) && (int) $value > 0
            ? (int) $value
            : (int) config('ai-core.llm.default_max_tokens', 2048);
    }

    /**
     * Premier bloc `text` de la réponse ; les blocs `thinking` sont ignorés.
     */
    private function firstText(mixed $content): string
    {
        if (! is_array($content)) {
            return '';
        }

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                return (string) ($block['text'] ?? '');
            }
        }

        return '';
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

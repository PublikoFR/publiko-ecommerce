<?php

declare(strict_types=1);

namespace Pko\AiCore\Llm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pko\AiCore\Enums\LlmProviderName;

/**
 * Traduit un alias de famille (`sonnet`, `opus`, `haiku`, `fable`) en
 * identifiant de modèle exact, le plus récent de la famille.
 *
 * L'API Anthropic n'accepte que des identifiants (`claude-sonnet-5-5`) : les
 * alias courts sont un raccourci applicatif. On interroge `GET /v1/models`
 * (trié du plus récent au plus ancien), résultat mis en cache
 * `ai-core.llm.aliases.cache_ttl` secondes ; table de repli en config si l'API
 * ne répond pas. Toute autre valeur (identifiant exact, modèle OpenAI) est
 * renvoyée telle quelle.
 */
final class ModelAliasResolver
{
    private const MODELS_ENDPOINT = 'https://api.anthropic.com/v1/models';

    public function isAlias(LlmProviderName $provider, string $model): bool
    {
        return array_key_exists(strtolower(trim($model)), $this->fallbacks($provider));
    }

    public function resolve(LlmProviderName $provider, string $model, string $apiKey): string
    {
        $alias = strtolower(trim($model));
        if (! $this->isAlias($provider, $alias)) {
            return trim($model);
        }

        $key = "ai-core:model-alias:{$provider->value}:{$alias}";

        return Cache::remember(
            $key,
            (int) config('ai-core.llm.aliases.cache_ttl', 86400),
            fn (): string => $this->latest($provider, $alias, $apiKey) ?? $this->fallbacks($provider)[$alias],
        );
    }

    private function latest(LlmProviderName $provider, string $alias, string $apiKey): ?string
    {
        if ($provider !== LlmProviderName::Claude) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'])
                ->get(self::MODELS_ENDPOINT, ['limit' => 100]);
        } catch (\Throwable) {
            return null;
        }
        if ($response->failed()) {
            return null;
        }

        $models = array_filter(
            (array) $response->json('data'),
            fn ($m): bool => is_array($m) && str_starts_with((string) ($m['id'] ?? ''), "claude-{$alias}-"),
        );
        usort($models, fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return isset($models[0]['id']) ? (string) $models[0]['id'] : null;
    }

    /**
     * @return array<string, string>
     */
    private function fallbacks(LlmProviderName $provider): array
    {
        return (array) config("ai-core.llm.aliases.{$provider->value}.fallback", []);
    }
}

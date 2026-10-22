<?php

declare(strict_types=1);

namespace Pko\AiCore\Llm;

use Pko\AiCore\Contracts\LlmProviderInterface;
use Pko\AiCore\Enums\LlmProviderName;
use Pko\AiCore\Llm\Providers\ClaudeProvider;
use Pko\AiCore\Llm\Providers\OpenAiProvider;
use Pko\AiCore\Models\LlmConfig;

final class LlmManager
{
    /**
     * Instancie un provider depuis des paramètres bruts — point d'entrée universel pour toute feature IA.
     *
     * @param  array<string, mixed>  $options
     */
    public function make(LlmProviderName $provider, string $apiKey, string $model, array $options = []): LlmProviderInterface
    {
        return match ($provider) {
            LlmProviderName::Claude => new ClaudeProvider($apiKey, $model, $options),
            LlmProviderName::OpenAi => new OpenAiProvider($apiKey, $model, $options),
        };
    }

    /**
     * Instancie un provider désigné par fournisseur + modèle, sans dépendre d'un
     * id de configuration (portable d'un environnement à l'autre). La clé API
     * vient de la configuration active de ce fournisseur (celle « par défaut »
     * d'abord) ; `$model` accepte un alias de famille (`sonnet`) ou un
     * identifiant exact, vide = modèle de cette configuration.
     *
     * @throws LlmUnavailableException aucune configuration active pour ce fournisseur
     */
    public function forBackend(LlmProviderName|string $provider, ?string $model = null): LlmProviderInterface
    {
        [$config, $resolved] = $this->resolveBackend($provider, $model);

        return $this->make(
            $config->provider,
            $config->api_key,
            $resolved,
            $config->options ? $config->options->getArrayCopy() : [],
        );
    }

    /**
     * @return array{0: LlmConfig, 1: string} configuration retenue + identifiant de modèle résolu
     *
     * @throws LlmUnavailableException
     */
    public function resolveBackend(LlmProviderName|string $provider, ?string $model = null): array
    {
        $name = $provider instanceof LlmProviderName
            ? $provider
            : LlmProviderName::tryFrom(strtolower(trim($provider)));
        if ($name === null) {
            throw new LlmUnavailableException("Fournisseur IA « {$provider} » inconnu (attendu : "
                .implode(', ', array_column(LlmProviderName::cases(), 'value')).').');
        }

        $config = LlmConfig::query()
            ->where('provider', $name->value)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
        if ($config === null) {
            throw new LlmUnavailableException("Aucune configuration IA active pour le fournisseur « {$name->value} » : ajoutez sa clé API dans Configurations LLM.");
        }

        $model = trim((string) $model) !== '' ? (string) $model : $config->model;

        return [$config, app(ModelAliasResolver::class)->resolve($name, $model, $config->api_key)];
    }

    /**
     * Instancie un provider depuis un LlmConfig Eloquent.
     */
    public function forConfig(LlmConfig $config): LlmProviderInterface
    {
        return $this->make(
            $config->provider,
            $config->api_key,
            $config->model,
            $config->options ? $config->options->getArrayCopy() : [],
        );
    }
}

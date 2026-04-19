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

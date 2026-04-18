<?php

declare(strict_types=1);

namespace Pko\AiImporter\Llm;

use Pko\AiImporter\Contracts\LlmProviderInterface;
use Pko\AiImporter\Enums\LlmProviderName;
use Pko\AiImporter\Llm\Providers\ClaudeProvider;
use Pko\AiImporter\Llm\Providers\OpenAiProvider;
use Pko\AiImporter\Models\LlmConfig;

final class LlmManager
{
    public function forConfig(LlmConfig $config): LlmProviderInterface
    {
        return match ($config->provider) {
            LlmProviderName::Claude => new ClaudeProvider($config),
            LlmProviderName::OpenAi => new OpenAiProvider($config),
        };
    }
}

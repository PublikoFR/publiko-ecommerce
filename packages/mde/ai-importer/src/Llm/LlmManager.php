<?php

declare(strict_types=1);

namespace Mde\AiImporter\Llm;

use Mde\AiImporter\Contracts\LlmProviderInterface;
use Mde\AiImporter\Enums\LlmProviderName;
use Mde\AiImporter\Llm\Providers\ClaudeProvider;
use Mde\AiImporter\Llm\Providers\OpenAiProvider;
use Mde\AiImporter\Models\LlmConfig;

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

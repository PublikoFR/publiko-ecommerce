<?php

declare(strict_types=1);

namespace Mde\AiImporter\Enums;

enum LlmProviderName: string
{
    case Claude = 'claude';
    case OpenAi = 'openai';

    public function label(): string
    {
        return match ($this) {
            self::Claude => 'Anthropic Claude',
            self::OpenAi => 'OpenAI',
        };
    }
}

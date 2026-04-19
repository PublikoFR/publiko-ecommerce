<?php

declare(strict_types=1);

namespace Pko\AiCore\Enums;

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

    /** @return array<string, string> slug => label */
    public function models(): array
    {
        return match ($this) {
            self::Claude => [
                'claude-opus-4-7' => 'Claude Opus 4.7',
                'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                'claude-haiku-4-5' => 'Claude Haiku 4.5',
                'claude-opus-4-5' => 'Claude Opus 4.5',
            ],
            self::OpenAi => [
                'gpt-4o' => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'gpt-4-turbo' => 'GPT-4 Turbo',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            ],
        };
    }

    /**
     * Models grouped by provider label — for use in Filament grouped Select.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedModels(): array
    {
        $grouped = [];
        foreach (self::cases() as $provider) {
            $grouped[$provider->label()] = $provider->models();
        }

        return $grouped;
    }

    public static function providerForModel(string $model): ?self
    {
        foreach (self::cases() as $provider) {
            if (array_key_exists($model, $provider->models())) {
                return $provider;
            }
        }

        return null;
    }
}

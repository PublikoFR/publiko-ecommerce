<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions;

use Mde\AiImporter\Actions\Types\ChangeCaseAction;
use Mde\AiImporter\Actions\Types\ConcatAction;
use Mde\AiImporter\Actions\Types\CopyAction;
use Mde\AiImporter\Actions\Types\DateFormatAction;
use Mde\AiImporter\Actions\Types\FeatureBuildAction;
use Mde\AiImporter\Actions\Types\LlmTransformAction;
use Mde\AiImporter\Actions\Types\MapAction;
use Mde\AiImporter\Actions\Types\MathAction;
use Mde\AiImporter\Actions\Types\MultilineAggregateAction;
use Mde\AiImporter\Actions\Types\RegexReplaceAction;
use Mde\AiImporter\Actions\Types\ReplaceAction;
use Mde\AiImporter\Actions\Types\RoundAction;
use Mde\AiImporter\Actions\Types\SlugifyAction;
use Mde\AiImporter\Actions\Types\TemplateAction;
use Mde\AiImporter\Actions\Types\TrimAction;
use Mde\AiImporter\Actions\Types\TruncateAction;
use Mde\AiImporter\Actions\Types\ValidateEan13Action;

/**
 * Runtime registry mapping a type string to an Action class.
 *
 * Seeded with the 17 built-in actions (Proposition D from SIMPLIFICATION.md).
 * Packages may add their own via `ActionRegistry::register()` in a boot hook.
 */
final class ActionRegistry
{
    /** @var array<string, class-string<Action>> */
    private static array $map = [];

    public static function bootDefaults(): void
    {
        if (self::$map !== []) {
            return;
        }

        foreach ([
            MathAction::class,
            RoundAction::class,
            ChangeCaseAction::class,
            TrimAction::class,
            TruncateAction::class,
            SlugifyAction::class,
            ReplaceAction::class,
            RegexReplaceAction::class,
            DateFormatAction::class,
            ValidateEan13Action::class,
            ConcatAction::class,
            TemplateAction::class,
            CopyAction::class,
            MapAction::class,
            LlmTransformAction::class,
            MultilineAggregateAction::class,
            FeatureBuildAction::class,
        ] as $class) {
            self::register($class::type(), $class);
        }
    }

    /**
     * @param  class-string<Action>  $class
     */
    public static function register(string $type, string $class): void
    {
        self::$map[$type] = $class;
    }

    /**
     * @return class-string<Action>
     */
    public static function resolve(string $type): string
    {
        self::bootDefaults();

        if (! isset(self::$map[$type])) {
            throw new \InvalidArgumentException("Unknown action type: {$type}");
        }

        return self::$map[$type];
    }

    /**
     * @return array<string, class-string<Action>>
     */
    public static function all(): array
    {
        self::bootDefaults();

        return self::$map;
    }
}

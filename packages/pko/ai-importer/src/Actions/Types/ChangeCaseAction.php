<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

final class ChangeCaseAction extends Action
{
    /** Types legacy PrestaShop fusionnés vers `change_case` (cf. ActionRegistry). */
    private const LEGACY_TYPE_TO_MODE = [
        'uppercase' => 'upper',
        'lowercase' => 'lower',
        'capitalize' => 'capitalize',
    ];

    public function __construct(public readonly string $mode = 'lower') {} // lower|upper|capitalize

    public static function type(): string
    {
        return 'change_case';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): static
    {
        $type = (string) ($config['type'] ?? 'change_case');
        unset($config['type']);

        // Alias legacy : {"type":"uppercase"} → mode=upper (sur le modèle de MathAction).
        if (! isset($config['mode']) && isset(self::LEGACY_TYPE_TO_MODE[$type])) {
            $config['mode'] = self::LEGACY_TYPE_TO_MODE[$type];
        }

        return new self(...$config); // @phpstan-ignore-line
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $s = (string) $value;

        return match ($this->mode) {
            'upper' => mb_strtoupper($s),
            'lower' => mb_strtolower($s),
            'capitalize' => mb_convert_case($s, MB_CASE_TITLE),
            default => $s,
        };
    }
}

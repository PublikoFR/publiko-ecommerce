<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

final class ChangeCaseAction extends Action
{
    public function __construct(public readonly string $mode = 'lower') {} // lower|upper|capitalize

    public static function type(): string
    {
        return 'change_case';
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

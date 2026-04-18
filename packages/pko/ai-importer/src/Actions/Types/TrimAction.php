<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

final class TrimAction extends Action
{
    public function __construct(
        public readonly string $chars = " \t\n\r\0\x0B",
        public readonly string $side = 'both', // both|left|right
    ) {}

    public static function type(): string
    {
        return 'trim';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $s = (string) $value;

        return match ($this->side) {
            'left' => ltrim($s, $this->chars),
            'right' => rtrim($s, $this->chars),
            default => trim($s, $this->chars),
        };
    }
}

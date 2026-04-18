<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

final class MathAction extends Action
{
    public function __construct(
        public readonly string $operation = 'multiply', // multiply|divide|add|subtract
        public readonly float $value = 1.0,
    ) {}

    public static function type(): string
    {
        return 'math';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $n = is_numeric($value) ? (float) $value : 0.0;

        return match ($this->operation) {
            'multiply' => $n * $this->value,
            'divide' => $this->value !== 0.0 ? $n / $this->value : $n,
            'add' => $n + $this->value,
            'subtract' => $n - $this->value,
            default => $n,
        };
    }
}

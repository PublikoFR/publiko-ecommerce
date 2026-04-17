<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

final class RoundAction extends Action
{
    public function __construct(public readonly int $decimals = 2) {}

    public static function type(): string
    {
        return 'round';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        return is_numeric($value) ? round((float) $value, $this->decimals) : $value;
    }
}

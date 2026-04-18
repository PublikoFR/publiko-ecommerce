<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

final class CopyAction extends Action
{
    public function __construct(public readonly string $col = '') {}

    public static function type(): string
    {
        return 'copy';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        return $ctx->row[$this->col] ?? $value;
    }
}

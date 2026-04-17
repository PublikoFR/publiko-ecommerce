<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

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

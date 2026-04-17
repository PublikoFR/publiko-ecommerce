<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

final class ReplaceAction extends Action
{
    public function __construct(
        public readonly string $search = '',
        public readonly string $replace = '',
    ) {}

    public static function type(): string
    {
        return 'replace';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        return str_replace($this->search, $this->replace, (string) $value);
    }
}

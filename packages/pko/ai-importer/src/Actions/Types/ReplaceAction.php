<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

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

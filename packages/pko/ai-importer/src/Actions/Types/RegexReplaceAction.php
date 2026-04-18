<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

final class RegexReplaceAction extends Action
{
    public function __construct(
        public readonly string $pattern = '',
        public readonly string $replace = '',
    ) {}

    public static function type(): string
    {
        return 'regex_replace';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        if ($this->pattern === '') {
            return $value;
        }

        return preg_replace($this->pattern, $this->replace, (string) $value) ?? $value;
    }
}

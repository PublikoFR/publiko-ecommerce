<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

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

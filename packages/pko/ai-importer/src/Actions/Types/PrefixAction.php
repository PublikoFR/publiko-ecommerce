<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

final class PrefixAction extends Action
{
    public function __construct(
        public readonly string $text = '',
        public readonly string $separator = '',
    ) {}

    public static function type(): string
    {
        return 'prefix';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $s = (string) $value;
        if ($s === '') {
            return $this->text;
        }

        return $this->text.$this->separator.$s;
    }
}

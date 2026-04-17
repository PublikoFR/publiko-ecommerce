<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

final class TruncateAction extends Action
{
    public function __construct(
        public readonly int $length = 255,
        public readonly string $suffix = '',
    ) {}

    public static function type(): string
    {
        return 'truncate';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $s = (string) $value;
        if (mb_strlen($s) <= $this->length) {
            return $s;
        }

        return mb_substr($s, 0, $this->length - mb_strlen($this->suffix)).$this->suffix;
    }
}

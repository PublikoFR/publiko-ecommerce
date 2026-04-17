<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

final class ConcatAction extends Action
{
    /**
     * @param  array<int, string>  $sources  list of column keys read from `ctx->row`
     */
    public function __construct(
        public readonly array $sources = [],
        public readonly string $separator = ' ',
    ) {}

    public static function type(): string
    {
        return 'concat';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $parts = [];
        foreach ($this->sources as $col) {
            $parts[] = (string) ($ctx->row[$col] ?? '');
        }

        return implode($this->separator, array_filter($parts, static fn ($p) => $p !== ''));
    }
}

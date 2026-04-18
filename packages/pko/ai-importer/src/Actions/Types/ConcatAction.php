<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

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

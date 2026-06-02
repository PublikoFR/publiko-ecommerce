<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\Concerns\ResolvesSheetSources;
use Pko\AiImporter\Actions\ExecutionContext;

final class ConcatAction extends Action
{
    use ResolvesSheetSources;

    /**
     * Each source is either a column key read from the primary row (`ctx->row`,
     * legacy shape) or an object `{"col": "...", "sheet": "..."}` pointing at a
     * secondary sheet — cf. {@see ResolvesSheetSources::resolveSource()}.
     *
     * @param  array<int, string|array<string, mixed>>  $sources  column keys or `{col, sheet}` objects
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
        foreach ($this->sources as $source) {
            $parts[] = $this->resolveSource($source, $ctx);
        }

        return implode($this->separator, array_filter($parts, static fn ($p) => $p !== ''));
    }
}

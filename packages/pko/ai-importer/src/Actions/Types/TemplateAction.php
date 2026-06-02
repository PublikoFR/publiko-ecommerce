<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\Concerns\ResolvesSheetSources;
use Pko\AiImporter\Actions\ExecutionContext;

final class TemplateAction extends Action
{
    use ResolvesSheetSources;

    /**
     * Each source maps a placeholder name to either a column key read from the
     * primary row (`ctx->row`, legacy shape) or an object
     * `{"col": "...", "sheet": "..."}` pointing at a secondary sheet — cf.
     * {@see ResolvesSheetSources::resolveSource()}.
     *
     * @param  array<string, string|array<string, mixed>>  $sources  placeholder => column key or `{col, sheet}` object
     */
    public function __construct(
        public readonly string $template = '',
        public readonly array $sources = [],
    ) {}

    public static function type(): string
    {
        return 'template';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $out = $this->template;
        foreach ($this->sources as $placeholder => $source) {
            $out = str_replace('{'.$placeholder.'}', $this->resolveSource($source, $ctx), $out);
        }

        return $out;
    }
}

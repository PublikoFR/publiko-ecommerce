<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

final class TemplateAction extends Action
{
    /**
     * @param  array<string, string>  $sources  placeholder name => column key in row
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
        foreach ($this->sources as $placeholder => $col) {
            $out = str_replace('{'.$placeholder.'}', (string) ($ctx->row[$col] ?? ''), $out);
        }

        return $out;
    }
}

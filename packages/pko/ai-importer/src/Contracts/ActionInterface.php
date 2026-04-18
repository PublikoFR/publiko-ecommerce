<?php

declare(strict_types=1);

namespace Pko\AiImporter\Contracts;

use Pko\AiImporter\Actions\ExecutionContext;

interface ActionInterface
{
    /**
     * Execute the action against a value within the current row/job context.
     */
    public function execute(mixed $value, ExecutionContext $ctx): mixed;

    /**
     * The unique type identifier used in JSON config (e.g. "math", "llm_transform").
     */
    public static function type(): string;
}

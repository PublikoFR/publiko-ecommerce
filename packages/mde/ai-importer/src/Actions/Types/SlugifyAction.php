<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Illuminate\Support\Str;
use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

final class SlugifyAction extends Action
{
    public function __construct(public readonly bool $lowercase = true) {}

    public static function type(): string
    {
        return 'slugify';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $slug = Str::slug((string) $value);

        return $this->lowercase ? $slug : $slug;
    }
}

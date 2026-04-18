<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

/**
 * Lookup table (replaces PS `map` + `category_map`).
 *
 * - `multi_value=true` splits the incoming value by `,` and maps each token.
 * - `default` used when a token has no match. If `default=null`, unmatched tokens are dropped.
 */
final class MapAction extends Action
{
    /**
     * @param  array<string, string>  $values  lookup table keyed by source value
     */
    public function __construct(
        public readonly array $values = [],
        public readonly ?string $default = null,
        public readonly bool $multi_value = false,
        public readonly string $separator = ',',
    ) {}

    public static function type(): string
    {
        return 'map';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        if (! $this->multi_value) {
            return $this->values[(string) $value] ?? $this->default ?? $value;
        }

        $tokens = array_map('trim', explode($this->separator, (string) $value));
        $mapped = [];
        foreach ($tokens as $t) {
            if (isset($this->values[$t])) {
                $mapped[] = $this->values[$t];
            } elseif ($this->default !== null) {
                $mapped[] = $this->default;
            }
        }

        return implode($this->separator, $mapped);
    }
}

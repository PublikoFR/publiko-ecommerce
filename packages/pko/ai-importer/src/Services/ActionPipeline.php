<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

/**
 * Runs a chain of actions on a single source value within a given row context.
 *
 * Handles the optional top-level `condition` block (Proposition D): evaluate
 * rules against `row` before running the `actions` pipeline. On failure,
 * returns the `else` value (or the starting value) without executing actions.
 */
final class ActionPipeline
{
    /**
     * @param  array<string, mixed>  $columnConfig  `{col, sheet?, default?, prefix?, suffix?, condition?, else?, actions[]}`
     */
    public function run(mixed $initialValue, array $columnConfig, ExecutionContext $ctx): mixed
    {
        $value = $initialValue ?? ($columnConfig['default'] ?? null);

        // Condition gate evaluated on the RAW source value (before prefix/suffix),
        // so `col_value` rules match the unaffixed column content — as the legacy
        // PrestaShop processor does (`getColumnValue()` ignores the column prefix).
        if (isset($columnConfig['condition'])
            && ! $this->evaluateCondition($columnConfig['condition'], $ctx, $value)
        ) {
            return $columnConfig['else'] ?? $value;
        }

        // Column-level prefix/suffix (legacy PS mapping format): plain concatenation
        // on the source value, applied before the actions pipeline. Empty values are
        // left untouched (PS `actionPrefix` early-returns '' on empty input).
        $value = $this->applyAffixes($value, $columnConfig);

        $actions = $columnConfig['actions'] ?? [];
        if (! is_array($actions)) {
            return $value;
        }

        foreach ($actions as $actionConfig) {
            if (! is_array($actionConfig)) {
                continue;
            }
            $action = Action::make($actionConfig);
            $value = $action->execute($value, $ctx);
        }

        return $value;
    }

    /**
     * Prepend `prefix` and append `suffix` (column-level legacy mapping keys) to
     * a non-empty value via plain concatenation (no separator). Null/empty values
     * are returned unchanged.
     *
     * @param  array<string, mixed>  $columnConfig
     */
    private function applyAffixes(mixed $value, array $columnConfig): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $prefix = $columnConfig['prefix'] ?? null;
        $suffix = $columnConfig['suffix'] ?? null;

        if (($prefix === null || $prefix === '') && ($suffix === null || $suffix === '')) {
            return $value;
        }

        return (string) ($prefix ?? '').(string) $value.(string) ($suffix ?? '');
    }

    /**
     * @param  array<string, mixed>  $condition  `{field, operator, value}` | `{branches, logic, ...}`
     * @param  mixed  $currentValue  the current pipeline value, used for `col_value` rules
     */
    private function evaluateCondition(array $condition, ExecutionContext $ctx, mixed $currentValue): bool
    {
        // Simple form: {field, operator, value}
        if (isset($condition['operator'])) {
            $field = $condition['field'] ?? 'col_value';
            $left = $field === 'col_value'
                ? $currentValue
                : ($ctx->row[$field] ?? null);

            return $this->compare($left, $condition['operator'], $condition['value'] ?? null);
        }

        // Advanced form (branches/rules) is handled by the ConditionAction itself.
        return true;
    }

    private function compare(mixed $left, string $op, mixed $right): bool
    {
        return match ($op) {
            '=', '==' => (string) $left === (string) $right,
            '!=', '<>' => (string) $left !== (string) $right,
            '>' => (float) $left > (float) $right,
            '>=' => (float) $left >= (float) $right,
            '<' => (float) $left < (float) $right,
            '<=' => (float) $left <= (float) $right,
            'contains' => is_string($left) && str_contains($left, (string) $right),
            'not_contains' => is_string($left) && ! str_contains($left, (string) $right),
            'empty' => $left === null || $left === '',
            'not_empty' => $left !== null && $left !== '',
            default => false,
        };
    }
}

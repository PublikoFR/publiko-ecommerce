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
     * @param  array<string, mixed>  $columnConfig  `{col, sheet?, default?, condition?, else?, actions[]}`
     */
    public function run(mixed $initialValue, array $columnConfig, ExecutionContext $ctx): mixed
    {
        $value = $initialValue ?? ($columnConfig['default'] ?? null);

        if (isset($columnConfig['condition'])
            && ! $this->evaluateCondition($columnConfig['condition'], $ctx)
        ) {
            return $columnConfig['else'] ?? $value;
        }

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
     * @param  array<string, mixed>  $condition  `{field, operator, value}` | `{branches, logic, ...}`
     */
    private function evaluateCondition(array $condition, ExecutionContext $ctx): bool
    {
        // Simple form: {field, operator, value}
        if (isset($condition['operator'])) {
            $field = $condition['field'] ?? 'col_value';
            $left = $field === 'col_value'
                ? ($ctx->getOutput('__current__') ?? null)
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

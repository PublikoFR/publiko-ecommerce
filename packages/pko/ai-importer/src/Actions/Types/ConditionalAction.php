<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

/**
 * Ternaire simple PrestaShop : évalue une condition sur la valeur courante et
 * retourne `if_true` ou `if_false`. Distinct du `condition` à branches
 * ({@see ConditionAction}) — ici aucune branche ni action imbriquée, juste un
 * ternaire scalaire.
 *
 * Config shape (matches PS, cf. mapping « Disponible à la commande ») :
 *
 * ```json
 * {
 *   "type": "conditional",
 *   "condition": "> 0",
 *   "if_true": "1",
 *   "if_false": "0"
 * }
 * ```
 *
 * Semantics :
 *   - `condition` = un opérateur suivi d'un opérande, ex. `"> 0"`, `">= 5"`,
 *     `"= X"`, `"!= ABC"`, `"contains foo"`. L'opérateur est parsé en tête de
 *     chaîne ; le reste (trim) est l'opérande de comparaison.
 *   - Une condition sans opérateur reconnu est traitée comme une égalité
 *     (`"GTK"` ⇔ `"= GTK"`).
 *   - Opérateurs : `=`/`==`, `!=`/`<>`, `>`, `>=`, `<`, `<=`, `contains`,
 *     `not_contains`, `empty`, `not_empty`. Les comparaisons numériques
 *     exigent des opérandes numériques des deux côtés.
 */
final class ConditionalAction extends Action
{
    public function __construct(
        public readonly string $condition = '',
        public readonly mixed $if_true = '',
        public readonly mixed $if_false = '',
    ) {}

    public static function type(): string
    {
        return 'conditional';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        return $this->evaluate($value) ? $this->if_true : $this->if_false;
    }

    private function evaluate(mixed $value): bool
    {
        $cond = trim($this->condition);
        if ($cond === '') {
            return false;
        }

        // Opérateurs « mots » d'abord (contains / empty…), puis symboliques.
        if (preg_match('/^(not_contains|contains|not_empty|empty)\b\s*(.*)$/', $cond, $m)) {
            $op = $m[1];
            $operand = trim($m[2]);
        } elseif (preg_match('/^(>=|<=|==|!=|<>|>|<|=)\s*(.*)$/', $cond, $m)) {
            $op = $m[1];
            $operand = trim($m[2]);
        } else {
            // Pas d'opérateur reconnu → égalité stricte sur la chaîne entière.
            $op = '=';
            $operand = $cond;
        }

        return $this->compare($value, $op, $operand);
    }

    private function compare(mixed $left, string $op, string $right): bool
    {
        return match ($op) {
            '=', '==' => (string) $left === $right,
            '!=', '<>' => (string) $left !== $right,
            '>' => is_numeric($left) && is_numeric($right) && (float) $left > (float) $right,
            '>=' => is_numeric($left) && is_numeric($right) && (float) $left >= (float) $right,
            '<' => is_numeric($left) && is_numeric($right) && (float) $left < (float) $right,
            '<=' => is_numeric($left) && is_numeric($right) && (float) $left <= (float) $right,
            'contains' => is_string($left) && str_contains($left, $right),
            'not_contains' => ! is_string($left) || ! str_contains($left, $right),
            'empty' => $left === null || $left === '',
            'not_empty' => $left !== null && $left !== '',
            default => false,
        };
    }
}

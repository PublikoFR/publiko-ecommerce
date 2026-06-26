<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Server-side renderer of the « CONFIGURATION » cell of the mapping blueprint —
 * a faithful, compact reproduction of the PrestaShop Publiko AI Importer row
 * summary : sheet badge + source column + default value, then the action
 * pipeline rendered as an arrow chain, with `condition` expanded into coloured
 * SI / ALORS / SINON / PUIS blocks.
 *
 * Pure presentation, no state mutation → fully unit-testable (see
 * tests/Unit/AiImporter/PipelineSummaryTest). All dynamic text is escaped with
 * e(); only the structural markup is trusted.
 */
final class PipelineSummary
{
    /**
     * Render the full summary cell for one mapping row.
     *
     * @param  array<string, mixed>  $row  A mapping_repeater item: {col, sheet, default, actions[]}
     */
    public static function render(array $row): HtmlString
    {
        $head = self::sourceHead($row);
        $actions = self::normalizeActions($row['actions'] ?? []);

        if ($head === '' && $actions === []) {
            return new HtmlString('<span class="text-sm text-gray-400 dark:text-gray-500">— aucune configuration —</span>');
        }

        $chain = self::renderPipeline($actions);

        return new HtmlString(
            '<div class="flex flex-col gap-1 text-sm leading-snug">'
            .($head !== '' ? '<div class="flex flex-wrap items-center gap-1.5">'.$head.'</div>' : '')
            .($chain !== '' ? '<div class="flex flex-col gap-0.5">'.$chain.'</div>' : '')
            .'</div>'
        );
    }

    /**
     * Sheet badge + source column + default value (the non-pipeline part).
     *
     * @param  array<string, mixed>  $row
     */
    private static function sourceHead(array $row): string
    {
        $parts = [];

        $sheet = trim((string) ($row['sheet'] ?? ''));
        if ($sheet !== '') {
            $parts[] = '<span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">'.e($sheet).'</span>';
        }

        $col = trim((string) ($row['col'] ?? ''));
        if ($col !== '') {
            $parts[] = '<span class="font-mono font-semibold text-gray-700 dark:text-gray-200">'.e($col).'</span>';
        }

        $default = (string) ($row['default'] ?? '');
        if ($default !== '') {
            $parts[] = '<span class="text-xs text-gray-400 dark:text-gray-500">déf: '.e($default).'</span>';
        }

        return implode(' ', $parts);
    }

    /**
     * Render a list of actions as an arrow chain (each on its own line for
     * `condition`, inline otherwise).
     *
     * @param  array<int, array<string, mixed>>  $actions
     */
    private static function renderPipeline(array $actions): string
    {
        if ($actions === []) {
            return '';
        }

        $rows = [];
        $inline = [];

        $flush = static function () use (&$inline, &$rows): void {
            if ($inline !== []) {
                $rows[] = '<div class="flex flex-wrap items-center gap-1">'.implode('<span class="text-gray-300 dark:text-gray-600">→</span>', $inline).'</div>';
                $inline = [];
            }
        };

        foreach ($actions as $action) {
            $type = (string) ($action['type'] ?? '');
            if ($type === 'condition') {
                $flush();
                $rows[] = self::renderCondition($action);

                continue;
            }
            $inline[] = self::chip($action);
        }
        $flush();

        return implode('', $rows);
    }

    /**
     * A single inline action chip : coloured label + compact param hint.
     *
     * @param  array<string, mixed>  $action
     */
    private static function chip(array $action): string
    {
        $type = (string) ($action['type'] ?? '');
        [$label, $hint] = self::describe($type, $action);

        $inner = '<span class="font-medium text-primary-600 dark:text-primary-400">'.e($label).'</span>';
        if ($hint !== '') {
            $inner .= ' <span class="text-gray-500 dark:text-gray-400">'.e($hint).'</span>';
        }

        return '<span class="inline-flex items-center gap-1 rounded bg-primary-50 px-1.5 py-0.5 text-xs dark:bg-primary-950/40">'.$inner.'</span>';
    }

    /**
     * Expand a `condition` action into coloured SI / ALORS / SINON SI / SINON blocks.
     *
     * @param  array<string, mixed>  $action
     */
    private static function renderCondition(array $action): string
    {
        $blocks = [];
        $branches = self::normalizeActions($action['branches'] ?? []);

        foreach ($branches as $i => $branch) {
            $branch = (array) $branch;
            $rules = self::renderRules((array) ($branch['rules'] ?? []), strtoupper((string) ($branch['logic'] ?? 'AND')));
            $then = self::inlineChips((array) ($branch['actions'] ?? []));
            $kw = $i === 0 ? 'SI' : 'SINON SI';
            $blocks[] = '<div class="flex flex-wrap items-center gap-1">'
                .'<span class="font-semibold text-warning-600 dark:text-warning-400">'.$kw.'</span> '
                .$rules
                .' <span class="font-semibold text-success-600 dark:text-success-400">ALORS</span> '
                .($then !== '' ? $then : '<span class="text-gray-400">—</span>')
                .'</div>';
        }

        $else = self::inlineChips((array) ($action['else_actions'] ?? []));
        if ($else !== '') {
            $blocks[] = '<div class="flex flex-wrap items-center gap-1">'
                .'<span class="font-semibold text-danger-600 dark:text-danger-400">SINON</span> '
                .$else
                .'</div>';
        }

        return '<div class="flex flex-col gap-0.5 border-l-2 border-warning-300 pl-2 dark:border-warning-700">'.implode('', $blocks).'</div>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    private static function renderRules(array $rules, string $logic): string
    {
        $glue = $logic === 'OR' ? ' OU ' : ' ET ';
        $parts = [];
        foreach ($rules as $rule) {
            $rule = (array) $rule;
            $field = (string) ($rule['field'] ?? '');
            $op = (string) ($rule['operator'] ?? '=');
            $val = (string) ($rule['value'] ?? '');
            if ($field === '') {
                continue;
            }
            $parts[] = '<span class="font-mono text-gray-700 dark:text-gray-200">'.e($field).'</span> '
                .'<span class="text-gray-500">'.e($op).'</span> '
                .'<span class="font-mono text-gray-700 dark:text-gray-200">'.e($val).'</span>';
        }

        return implode('<span class="text-gray-400">'.e($glue).'</span>', $parts);
    }

    /**
     * Inline-render a nested actions list (used inside condition branches).
     *
     * @param  array<int, mixed>  $actions
     */
    private static function inlineChips(array $actions): string
    {
        $chips = [];
        foreach (self::normalizeActions($actions) as $a) {
            $type = (string) ($a['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $chips[] = self::chip($a);
        }

        return implode('<span class="text-gray-300 dark:text-gray-600">→</span>', $chips);
    }

    /**
     * Human label + compact param hint for one action type. Falls back to the
     * curated {@see ActionPalette} label and a generic param dump.
     *
     * @param  array<string, mixed>  $action
     * @return array{0: string, 1: string}
     */
    private static function describe(string $type, array $action): array
    {
        $value = static fn (string $k, mixed $default = ''): string => self::scalar($action[$k] ?? $default);

        return match ($type) {
            'multiply' => ['Multiplier', '×'.$value('value', '1')],
            'divide' => ['Diviser', '÷'.$value('value', '1')],
            'add' => ['Additionner', '+'.$value('value', '0')],
            'subtract' => ['Soustraire', '−'.$value('value', '0')],
            'math' => [self::mathLabel($value('operation', 'multiply')), self::mathSymbol($value('operation', 'multiply')).$value('value', '1')],
            'round' => ['Arrondir', '('.$value('decimals', '2').')'],
            'truncate' => ['Tronquer', $value('length', '255').' car.'],
            'copy' => ['Copier', $value('col')],
            'prefix' => ['Préfixe', self::quote($value('text'))],
            'suffix' => ['Suffixe', self::quote($value('text'))],
            'replace' => ['Remplacer', self::quote($value('search')).' → '.self::quote($value('replace'))],
            'regex_replace' => ['Remplacer (regex)', self::quote($value('pattern'))],
            'slugify' => ['Slug', ''],
            'trim' => ['Nettoyer espaces', ''],
            'change_case' => ['Casse', $value('mode')],
            'concat' => ['Concaténer', self::countHint($action['sources'] ?? [], 'source')],
            'template' => ['Template', self::quote($value('template'))],
            'map' => ['Table de corresp.', self::countHint($action['values'] ?? [], 'entrée')],
            'category_map', 'parse_category_breadcrumb' => ['Mapping catégories', ''],
            'date_format' => ['Format date', $value('format')],
            'validate_ean13' => ['Valider EAN-13', ''],
            'llm_transform' => ['Prompt IA', $value('output_format') === 'json' ? 'JSON' : ''],
            'multiline_aggregate' => ['Multi-lignes', trim($value('method').' '.($value('filter_type') !== '' ? '['.$value('filter_type').']' : ''))],
            'feature_build', 'parse_features_string' => ['Caractéristiques', ''],
            default => [ActionPalette::label($type), self::genericHint($action)],
        };
    }

    private static function mathLabel(string $op): string
    {
        return match ($op) {
            'divide' => 'Diviser',
            'add' => 'Additionner',
            'subtract' => 'Soustraire',
            default => 'Multiplier',
        };
    }

    private static function mathSymbol(string $op): string
    {
        return match ($op) {
            'divide' => '÷',
            'add' => '+',
            'subtract' => '−',
            default => '×',
        };
    }

    /**
     * Normalise an actions/branches list to a list of arrays, dropping scalars.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeActions(mixed $actions): array
    {
        $out = [];
        foreach ((array) $actions as $a) {
            if (is_array($a)) {
                $out[] = $a;
            }
        }

        return $out;
    }

    private static function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }
        if (is_float($value)) {
            // 1.2 → "1.2", 10.0 → "10" (pas de ".0" parasite dans le résumé).
            return rtrim(rtrim(sprintf('%.4f', $value), '0'), '.');
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function quote(string $value): string
    {
        $value = trim($value);

        return $value === '' ? '' : '« '.Str::limit($value, 24).' »';
    }

    private static function countHint(mixed $items, string $noun): string
    {
        $n = is_array($items) ? count($items) : 0;
        if ($n === 0) {
            return '';
        }

        return $n.' '.$noun.($n > 1 ? 's' : '');
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private static function genericHint(array $action): string
    {
        unset($action['type'], $action['comment'], $action['branches'], $action['else_actions']);
        $bits = [];
        foreach ($action as $k => $v) {
            if (is_scalar($v) && (string) $v !== '') {
                $bits[] = $k.'='.Str::limit((string) $v, 16);
            }
            if (count($bits) >= 2) {
                break;
            }
        }

        return implode(' ', $bits);
    }
}

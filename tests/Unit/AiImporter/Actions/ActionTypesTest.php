<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter\Actions;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Models\ImportJob;

/**
 * Pure unit tests for the 17 action types — no DB, no container.
 *
 * `ExecutionContext` receives a non-persisted `ImportJob` because we don't
 * need a real row for actions that only look at `$value`, `$ctx->row`, or
 * `$ctx->sheets`.
 */
class ActionTypesTest extends TestCase
{
    private function ctx(array $row = [], array $sheets = []): ExecutionContext
    {
        return new ExecutionContext(job: new ImportJob, row: $row, sheets: $sheets);
    }

    public function test_math_multiply_then_round_chains_through_factory(): void
    {
        $multiply = Action::make(['type' => 'math', 'operation' => 'multiply', 'value' => 1.2]);
        $round = Action::make(['type' => 'round', 'decimals' => 2]);

        $result = $round->execute($multiply->execute(100, $this->ctx()), $this->ctx());

        $this->assertSame(120.0, $result);
    }

    public function test_math_divide_by_zero_is_noop(): void
    {
        $action = Action::make(['type' => 'math', 'operation' => 'divide', 'value' => 0]);

        $this->assertSame(42.0, $action->execute(42, $this->ctx()));
    }

    public function test_change_case_handles_three_modes(): void
    {
        $upper = Action::make(['type' => 'change_case', 'mode' => 'upper']);
        $lower = Action::make(['type' => 'change_case', 'mode' => 'lower']);
        $capitalize = Action::make(['type' => 'change_case', 'mode' => 'capitalize']);

        $this->assertSame('SOMFY', $upper->execute('Somfy', $this->ctx()));
        $this->assertSame('somfy', $lower->execute('SOMFY', $this->ctx()));
        $this->assertSame('Somfy Rts', $capitalize->execute('somfy rts', $this->ctx()));
    }

    public function test_truncate_appends_suffix_and_respects_length(): void
    {
        $action = Action::make(['type' => 'truncate', 'length' => 10, 'suffix' => '…']);

        $this->assertSame('Lorem ips…', $action->execute('Lorem ipsum dolor', $this->ctx()));
    }

    public function test_truncate_noop_when_short_enough(): void
    {
        $action = Action::make(['type' => 'truncate', 'length' => 50]);

        $this->assertSame('short', $action->execute('short', $this->ctx()));
    }

    public function test_concat_joins_other_columns_from_row(): void
    {
        $action = Action::make(['type' => 'concat', 'sources' => ['brand', 'model'], 'separator' => ' - ']);

        $this->assertSame('Somfy - RTS', $action->execute(null, $this->ctx(['brand' => 'Somfy', 'model' => 'RTS'])));
    }

    public function test_template_interpolates_named_placeholders(): void
    {
        $action = Action::make([
            'type' => 'template',
            'template' => '{brand} {name} ({sku})',
            'sources' => ['brand' => 'brand_col', 'name' => 'name_col', 'sku' => 'ref'],
        ]);

        $row = ['brand_col' => 'Somfy', 'name_col' => 'Moteur RS100', 'ref' => 'R-123'];

        $this->assertSame('Somfy Moteur RS100 (R-123)', $action->execute(null, $this->ctx($row)));
    }

    public function test_map_single_value(): void
    {
        $action = Action::make([
            'type' => 'map',
            'values' => ['A' => 'Actif', 'I' => 'Inactif'],
            'default' => 'Inconnu',
        ]);

        $this->assertSame('Actif', $action->execute('A', $this->ctx()));
        $this->assertSame('Inconnu', $action->execute('Z', $this->ctx()));
    }

    public function test_map_multi_value_splits_on_separator(): void
    {
        $action = Action::make([
            'type' => 'map',
            'values' => ['red' => 'Rouge', 'blue' => 'Bleu'],
            'default' => null,
            'multi_value' => true,
        ]);

        $this->assertSame('Rouge,Bleu', $action->execute('red,blue,yellow', $this->ctx()));
    }

    public function test_validate_ean13_accepts_valid_and_rejects_bad_checksum(): void
    {
        $action = Action::make(['type' => 'validate_ean13']);

        $this->assertSame('3017620422003', $action->execute('3017620422003', $this->ctx()));
        $this->assertSame('', $action->execute('3017620422002', $this->ctx()));
        $this->assertSame('', $action->execute('nope', $this->ctx()));
    }

    public function test_slugify_produces_url_safe_string(): void
    {
        $action = Action::make(['type' => 'slugify']);

        $this->assertSame('portail-aluminium-2m', $action->execute('Portail Aluminium 2M !', $this->ctx()));
    }

    public function test_replace_and_regex_replace(): void
    {
        $replace = Action::make(['type' => 'replace', 'search' => 'FR', 'replace' => 'France']);
        $regex = Action::make(['type' => 'regex_replace', 'pattern' => '/\\s+/', 'replace' => '-']);

        $this->assertSame('Made in France', $replace->execute('Made in FR', $this->ctx()));
        $this->assertSame('ab-cd-ef', $regex->execute("ab  cd\tef", $this->ctx()));
    }

    public function test_copy_returns_other_column(): void
    {
        $action = Action::make(['type' => 'copy', 'col' => 'src']);

        $this->assertSame('hello', $action->execute('original', $this->ctx(['src' => 'hello'])));
    }

    public function test_trim_respects_side(): void
    {
        $left = Action::make(['type' => 'trim', 'side' => 'left']);
        $right = Action::make(['type' => 'trim', 'side' => 'right']);

        $this->assertSame('foo  ', $left->execute('  foo  ', $this->ctx()));
        $this->assertSame('  foo', $right->execute('  foo  ', $this->ctx()));
    }

    public function test_date_format_converts_between_patterns(): void
    {
        $action = Action::make(['type' => 'date_format', 'from' => 'Y-m-d', 'to' => 'd/m/Y']);

        $this->assertSame('17/04/2026', $action->execute('2026-04-17', $this->ctx()));
    }

    public function test_multiline_aggregate_concat_filters_and_joins(): void
    {
        $sheets = [
            'B02' => [
                ['type' => 'CODE_IMAGE', 'url' => 'a.jpg'],
                ['type' => 'CODE_DOC', 'url' => 'manual.pdf'],
                ['type' => 'CODE_IMAGE', 'url' => 'b.jpg'],
            ],
        ];

        $action = Action::make([
            'type' => 'multiline_aggregate',
            'sheet' => 'B02',
            'method' => 'concat',
            'separator' => '|',
            'filter_type' => 'CODE_IMAGE',
            'columns' => ['url'],
        ]);

        $this->assertSame('a.jpg|b.jpg', $action->execute(null, $this->ctx([], $sheets)));
    }

    public function test_multiline_aggregate_count(): void
    {
        $sheets = ['X' => [['foo' => 1], ['foo' => 2], ['foo' => 3]]];
        $action = Action::make(['type' => 'multiline_aggregate', 'sheet' => 'X', 'method' => 'count', 'columns' => ['foo']]);

        $this->assertSame(3, $action->execute(null, $this->ctx([], $sheets)));
    }
}

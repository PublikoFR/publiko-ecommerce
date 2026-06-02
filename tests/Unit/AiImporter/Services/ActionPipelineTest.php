<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter\Services;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Services\ActionPipeline;

class ActionPipelineTest extends TestCase
{
    public function test_chains_actions_in_order(): void
    {
        $pipeline = new ActionPipeline;
        $ctx = new ExecutionContext(job: new ImportJob);

        $result = $pipeline->run(100, [
            'actions' => [
                ['type' => 'math', 'operation' => 'multiply', 'value' => 1.2],
                ['type' => 'round', 'decimals' => 2],
            ],
        ], $ctx);

        $this->assertSame(120.0, $result);
    }

    public function test_default_is_used_when_initial_is_null(): void
    {
        $pipeline = new ActionPipeline;
        $ctx = new ExecutionContext(job: new ImportJob);

        $result = $pipeline->run(null, [
            'default' => 'n/a',
            'actions' => [
                ['type' => 'change_case', 'mode' => 'upper'],
            ],
        ], $ctx);

        $this->assertSame('N/A', $result);
    }

    public function test_condition_false_returns_else_branch(): void
    {
        $pipeline = new ActionPipeline;
        $ctx = new ExecutionContext(job: new ImportJob, row: ['price' => 0]);

        $result = $pipeline->run(0, [
            'condition' => ['field' => 'price', 'operator' => '>', 'value' => 10],
            'else' => 'FREE',
            'actions' => [
                ['type' => 'math', 'operation' => 'multiply', 'value' => 1.2],
            ],
        ], $ctx);

        $this->assertSame('FREE', $result);
    }

    public function test_condition_true_runs_pipeline(): void
    {
        $pipeline = new ActionPipeline;
        $ctx = new ExecutionContext(job: new ImportJob, row: ['price' => 100]);

        $result = $pipeline->run(100, [
            'condition' => ['field' => 'price', 'operator' => '>', 'value' => 10],
            'else' => 'FREE',
            'actions' => [
                ['type' => 'math', 'operation' => 'multiply', 'value' => 1.2],
            ],
        ], $ctx);

        $this->assertSame(120.0, $result);
    }

    public function test_column_prefix_and_suffix_are_applied_to_source_value(): void
    {
        $pipeline = new ActionPipeline;
        $ctx = new ExecutionContext(job: new ImportJob);

        $result = $pipeline->run('REFCIALE', [
            'prefix' => 'FAA',
            'suffix' => '-X',
        ], $ctx);

        $this->assertSame('FAAREFCIALE-X', $result);
    }

    public function test_column_prefix_runs_before_actions(): void
    {
        $pipeline = new ActionPipeline;
        $ctx = new ExecutionContext(job: new ImportJob);

        // upper-case action must see the already-prefixed value
        $result = $pipeline->run('faa', [
            'prefix' => 'pre-',
            'actions' => [['type' => 'change_case', 'mode' => 'upper']],
        ], $ctx);

        $this->assertSame('PRE-FAA', $result);
    }

    public function test_column_prefix_left_untouched_on_empty_value(): void
    {
        $pipeline = new ActionPipeline;
        $ctx = new ExecutionContext(job: new ImportJob);

        $this->assertSame('', $pipeline->run('', ['prefix' => 'FAA'], $ctx));
        $this->assertNull($pipeline->run(null, ['prefix' => 'FAA'], $ctx));
    }

    public function test_col_value_condition_sees_raw_unprefixed_value(): void
    {
        $pipeline = new ActionPipeline;
        $ctx = new ExecutionContext(job: new ImportJob);

        // not_empty must evaluate the raw column value, not the prefixed one,
        // so a present reference passes and gets the prefix applied afterwards.
        $present = $pipeline->run('R-1', [
            'prefix' => 'FAA',
            'condition' => ['field' => 'col_value', 'operator' => 'not_empty'],
            'else' => '',
        ], $ctx);
        $this->assertSame('FAAR-1', $present);

        // Empty reference fails not_empty → else branch, unprefixed.
        $missing = $pipeline->run('', [
            'prefix' => 'FAA',
            'condition' => ['field' => 'col_value', 'operator' => 'not_empty'],
            'else' => '',
        ], $ctx);
        $this->assertSame('', $missing);
    }

    public function test_unknown_action_type_throws(): void
    {
        $pipeline = new ActionPipeline;
        $ctx = new ExecutionContext(job: new ImportJob);

        $this->expectException(\InvalidArgumentException::class);

        $pipeline->run('x', [
            'actions' => [['type' => 'nope']],
        ], $ctx);
    }
}

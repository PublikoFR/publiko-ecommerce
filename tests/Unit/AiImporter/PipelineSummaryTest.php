<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Support\PipelineSummary;

/**
 * Pure rendering guard for the blueprint « CONFIGURATION » cell. No DB, no
 * Filament — asserts the textual content of the produced HtmlString for the
 * action shapes found in real PrestaShop configs (SOMFY) plus the `condition`
 * branching that the import editor must surface.
 */
class PipelineSummaryTest extends TestCase
{
    private function html(array $row): string
    {
        return PipelineSummary::render($row)->toHtml();
    }

    public function test_empty_row_renders_placeholder(): void
    {
        $this->assertStringContainsString('aucune configuration', $this->html([]));
    }

    public function test_source_head_shows_sheet_column_and_default(): void
    {
        $html = $this->html(['sheet' => 'B01_COMMERCE', 'col' => 'M', 'default' => '0']);

        $this->assertStringContainsString('B01_COMMERCE', $html);
        $this->assertStringContainsString('M', $html);
        $this->assertStringContainsString('déf: 0', $html);
    }

    public function test_math_aliases_render_symbols(): void
    {
        $this->assertStringContainsString('×1.2', $this->html(['actions' => [['type' => 'multiply', 'value' => 1.2]]]));
        $this->assertStringContainsString('÷10', $this->html(['actions' => [['type' => 'divide', 'value' => 10]]]));
    }

    public function test_prefix_and_concat_hints(): void
    {
        $this->assertStringContainsString('SOM', $this->html(['actions' => [['type' => 'prefix', 'text' => 'SOM']]]));

        $concat = $this->html(['actions' => [['type' => 'concat', 'sources' => [['col' => 'A'], ['col' => 'B'], ['col' => 'C']]]]]);
        $this->assertStringContainsString('Concaténer', $concat);
        $this->assertStringContainsString('3 sources', $concat);
    }

    public function test_llm_transform_labelled_prompt_ia(): void
    {
        $this->assertStringContainsString('Prompt IA', $this->html(['actions' => [['type' => 'llm_transform', 'prompt' => 'x']]]));
    }

    public function test_condition_expands_into_si_alors_sinon(): void
    {
        $html = $this->html([
            'actions' => [
                [
                    'type' => 'condition',
                    'branches' => [[
                        'logic' => 'AND',
                        'rules' => [['field' => 'AB', 'operator' => '=', 'value' => 'GTK']],
                        'actions' => [['type' => 'multiply', 'value' => 1.2]],
                    ]],
                    'else_actions' => [['type' => 'multiply', 'value' => 1.2]],
                ],
                ['type' => 'round', 'decimals' => 2],
            ],
        ]);

        $this->assertStringContainsString('SI', $html);
        $this->assertStringContainsString('ALORS', $html);
        $this->assertStringContainsString('SINON', $html);
        $this->assertStringContainsString('AB', $html);
        $this->assertStringContainsString('GTK', $html);
        $this->assertStringContainsString('Arrondir', $html);
    }

    public function test_dynamic_text_is_escaped(): void
    {
        $html = $this->html(['actions' => [['type' => 'prefix', 'text' => '<script>x']]]);

        $this->assertStringNotContainsString('<script>x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}

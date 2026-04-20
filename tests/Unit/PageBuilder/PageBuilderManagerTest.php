<?php

declare(strict_types=1);

namespace Tests\Unit\PageBuilder;

use PHPUnit\Framework\TestCase;
use Pko\PageBuilder\Services\PageBuilderManager;

final class PageBuilderManagerTest extends TestCase
{
    public function test_normalize_empty_payload_returns_empty_sections(): void
    {
        $this->assertSame(['sections' => []], PageBuilderManager::normalize(null));
        $this->assertSame(['sections' => []], PageBuilderManager::normalize([]));
    }

    public function test_normalize_fills_default_values(): void
    {
        $out = PageBuilderManager::normalize([
            'sections' => [
                ['layout' => '2col'],
            ],
        ]);

        $section = $out['sections'][0];
        $this->assertStringStartsWith('sec_', $section['id']);
        $this->assertSame('2col', $section['layout']);
        $this->assertSame(['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0], $section['padding']);
        $this->assertSame(['t' => 0, 'b' => 0], $section['margin']);
        $this->assertNull($section['background_color']);
        $this->assertNull($section['text_color']);
        $this->assertCount(2, $section['columns']);
    }

    public function test_invalid_layout_falls_back_to_1col(): void
    {
        $out = PageBuilderManager::normalize(['sections' => [['layout' => 'huge']]]);
        $this->assertSame('1col', $out['sections'][0]['layout']);
        $this->assertCount(1, $out['sections'][0]['columns']);
    }

    public function test_columns_are_truncated_or_padded_to_match_layout(): void
    {
        // layout = 1col but user passed 3 columns → keep only 1
        $out = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'columns' => [
                    ['blocks' => [['type' => 'text', 'html' => 'A']]],
                    ['blocks' => [['type' => 'text', 'html' => 'B']]],
                    ['blocks' => [['type' => 'text', 'html' => 'C']]],
                ],
            ]],
        ]);

        $this->assertCount(1, $out['sections'][0]['columns']);
        $this->assertSame('A', $out['sections'][0]['columns'][0]['blocks'][0]['html']);
    }

    public function test_unknown_block_types_are_dropped(): void
    {
        $out = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'columns' => [
                    ['blocks' => [
                        ['type' => 'text', 'html' => 'ok'],
                        ['type' => 'carousel', 'stuff' => 'nope'],
                    ]],
                ],
            ]],
        ]);

        $blocks = $out['sections'][0]['columns'][0]['blocks'];
        $this->assertCount(1, $blocks);
        $this->assertSame('text', $blocks[0]['type']);
    }

    public function test_colors_must_match_hex6_pattern(): void
    {
        $out = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'background_color' => '#FFAA00',
                'text_color' => 'rgb(0,0,0)',
            ]],
        ]);

        $this->assertSame('#ffaa00', $out['sections'][0]['background_color']);
        $this->assertNull($out['sections'][0]['text_color']);
    }

    public function test_padding_values_are_clamped(): void
    {
        $out = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'padding' => ['t' => -50, 'r' => 999, 'b' => 16, 'l' => 8],
            ]],
        ]);

        $this->assertSame(
            ['t' => 0, 'r' => 400, 'b' => 16, 'l' => 8],
            $out['sections'][0]['padding'],
        );
    }

    public function test_image_block_normalizes_media_id_and_alt(): void
    {
        $out = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'columns' => [['blocks' => [
                    ['type' => 'image', 'media_id' => '42', 'alt' => 'Produit'],
                ]]],
            ]],
        ]);

        $block = $out['sections'][0]['columns'][0]['blocks'][0];
        $this->assertSame('image', $block['type']);
        $this->assertSame(42, $block['media_id']);
        $this->assertSame('Produit', $block['alt']);
    }

    public function test_code_block_normalizes_language_allowlist(): void
    {
        $out = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'columns' => [['blocks' => [
                    ['type' => 'code', 'language' => 'php', 'content' => '<?php echo 1;'],
                    ['type' => 'code', 'language' => 'brainfuck', 'content' => '+'],
                ]]],
            ]],
        ]);

        $blocks = $out['sections'][0]['columns'][0]['blocks'];
        $this->assertSame('php', $blocks[0]['language']);
        $this->assertSame('plain', $blocks[1]['language']); // unknown → fallback
    }

    public function test_new_section_helper_returns_normalized_shape(): void
    {
        $s = PageBuilderManager::newSection('3col');
        $this->assertSame('3col', $s['layout']);
        $this->assertCount(3, $s['columns']);
        $this->assertStringStartsWith('sec_', $s['id']);
    }

    public function test_new_block_helper_returns_normalized_shape(): void
    {
        $b = PageBuilderManager::newBlock('code');
        $this->assertSame('code', $b['type']);
        $this->assertSame('plain', $b['language']);
        $this->assertSame('', $b['content']);

        $this->assertNull(PageBuilderManager::newBlock('nope'));
    }
}

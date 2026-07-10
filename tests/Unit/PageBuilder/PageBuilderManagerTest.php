<?php

declare(strict_types=1);

namespace Tests\Unit\PageBuilder;

use Pko\PageBuilder\Services\PageBuilderManager;
use Tests\TestCase;

final class PageBuilderManagerTest extends TestCase
{
    public function test_normalize_empty_payload_returns_empty_sections(): void
    {
        $this->assertSame(['heading' => '', 'sections' => []], PageBuilderManager::normalize(null));
        $this->assertSame(['heading' => '', 'sections' => []], PageBuilderManager::normalize([]));
    }

    public function test_normalize_heading_strips_tags_trims_and_bounds(): void
    {
        $out = PageBuilderManager::normalize(['heading' => '  <b>Envoyez-nous un message</b>  ', 'sections' => []]);
        $this->assertSame('Envoyez-nous un message', $out['heading']);

        // Non-string → chaîne vide
        $this->assertSame('', PageBuilderManager::normalize(['heading' => ['x'], 'sections' => []])['heading']);

        // Borné à 250 caractères
        $long = str_repeat('a', 300);
        $this->assertSame(250, mb_strlen(PageBuilderManager::normalize(['heading' => $long, 'sections' => []])['heading']));
    }

    public function test_quote_block_normalizes_text_and_cite(): void
    {
        $block = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'columns' => [['blocks' => [
                    ['type' => 'quote', 'text' => '  <i>Citation</i>  ', 'cite' => '<b>Auteur</b>'],
                ]]],
            ]],
        ])['sections'][0]['columns'][0]['blocks'][0];

        $this->assertSame('quote', $block['type']);
        $this->assertSame('Citation', $block['text']);
        $this->assertSame('Auteur', $block['cite']);
    }

    public function test_button_block_sanitizes_url_and_variant(): void
    {
        $blocks = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'columns' => [['blocks' => [
                    ['type' => 'button', 'label' => 'Voir', 'url' => 'https://ok.test', 'variant' => 'accent'],
                    ['type' => 'button', 'label' => 'Hack', 'url' => 'javascript:alert(1)', 'variant' => 'evil'],
                ]]],
            ]],
        ])['sections'][0]['columns'][0]['blocks'];

        $this->assertSame('https://ok.test', $blocks[0]['url']);
        $this->assertSame('accent', $blocks[0]['variant']);
        // javascript: neutralisé, variant inconnu → primary
        $this->assertSame('', $blocks[1]['url']);
        $this->assertSame('primary', $blocks[1]['variant']);
    }

    public function test_separator_block_normalizes_variant(): void
    {
        $blocks = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'columns' => [['blocks' => [
                    ['type' => 'separator', 'variant' => 'space'],
                    ['type' => 'separator', 'variant' => 'zigzag'],
                ]]],
            ]],
        ])['sections'][0]['columns'][0]['blocks'];

        $this->assertSame('space', $blocks[0]['variant']);
        $this->assertSame('line', $blocks[1]['variant']); // inconnu → line
    }

    public function test_new_block_helper_supports_new_types(): void
    {
        $this->assertSame('quote', PageBuilderManager::newBlock('quote')['type']);
        $this->assertSame('primary', PageBuilderManager::newBlock('button')['variant']);
        $this->assertSame('line', PageBuilderManager::newBlock('separator')['variant']);
    }

    public function test_layout_supports_up_to_six_columns(): void
    {
        $this->assertCount(6, PageBuilderManager::allowedLayouts());
        $this->assertSame(5, PageBuilderManager::columnsForLayout('5col'));
        $this->assertSame(6, PageBuilderManager::columnsForLayout('6col'));

        $out = PageBuilderManager::normalize(['sections' => [['layout' => '6col']]]);
        $this->assertSame('6col', $out['sections'][0]['layout']);
        $this->assertCount(6, $out['sections'][0]['columns']);
    }

    public function test_callout_block_normalizes_variant_and_text(): void
    {
        $blocks = PageBuilderManager::normalize([
            'sections' => [[
                'layout' => '1col',
                'columns' => [['blocks' => [
                    ['type' => 'callout', 'variant' => 'danger', 'text' => '  <b>Attention</b>  '],
                    ['type' => 'callout', 'variant' => 'nope', 'text' => 'x'],
                ]]],
            ]],
        ])['sections'][0]['columns'][0]['blocks'];

        $this->assertSame('callout', $blocks[0]['type']);
        $this->assertSame('danger', $blocks[0]['variant']);
        $this->assertSame('Attention', $blocks[0]['text']);
        $this->assertSame('info', $blocks[1]['variant']); // inconnu → info
    }

    public function test_new_block_callout_preset_maps_variant(): void
    {
        $this->assertSame('callout', PageBuilderManager::newBlock('callout-warning')['type']);
        $this->assertSame('warning', PageBuilderManager::newBlock('callout-warning')['variant']);
        $this->assertSame('danger', PageBuilderManager::newBlock('callout-danger')['variant']);
        // 'callout' nu → variante par défaut info
        $this->assertSame('info', PageBuilderManager::newBlock('callout')['variant']);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function normalizedBlock(array $raw): array
    {
        return PageBuilderManager::normalize([
            'sections' => [['layout' => '1col', 'columns' => [['blocks' => [$raw]]]]],
        ])['sections'][0]['columns'][0]['blocks'][0] ?? [];
    }

    public function test_title_block_normalizes_level_and_text(): void
    {
        $b = $this->normalizedBlock(['type' => 'title', 'level' => 'h3', 'text' => '  <b>Nos services</b>  ']);
        $this->assertSame('title', $b['type']);
        $this->assertSame('h3', $b['level']);
        $this->assertSame('Nos services', $b['text']);

        // niveau inconnu → h2
        $this->assertSame('h2', $this->normalizedBlock(['type' => 'title', 'level' => 'h1', 'text' => 'X'])['level']);
    }

    public function test_video_block_keeps_only_http_urls(): void
    {
        $ok = $this->normalizedBlock(['type' => 'video', 'url' => 'https://youtu.be/abc123']);
        $this->assertSame('https://youtu.be/abc123', $ok['url']);

        $bad = $this->normalizedBlock(['type' => 'video', 'url' => 'javascript:alert(1)']);
        $this->assertSame('', $bad['url']);
    }

    public function test_list_block_filters_and_bounds_items(): void
    {
        $b = $this->normalizedBlock([
            'type' => 'list',
            'style' => 'check',
            'items' => ['  Un  ', '', '<i>Deux</i>', 42, '   '],
        ]);
        $this->assertSame('check', $b['style']);
        $this->assertSame(['Un', 'Deux'], $b['items']); // vides et non-string retirés

        // style inconnu → bullet
        $this->assertSame('bullet', $this->normalizedBlock(['type' => 'list', 'style' => 'x', 'items' => ['a']])['style']);
    }

    public function test_accordion_block_normalizes_items(): void
    {
        $b = $this->normalizedBlock([
            'type' => 'accordion',
            'items' => [
                ['q' => '  Q1 ', 'a' => ' R1 '],
                ['q' => '', 'a' => ''],   // vide → retiré
                ['nope' => true],          // non conforme → retiré
            ],
        ]);
        $this->assertSame('accordion', $b['type']);
        $this->assertCount(1, $b['items']);
        $this->assertSame(['q' => 'Q1', 'a' => 'R1'], $b['items'][0]);
    }

    public function test_gallery_block_dedupes_ids_and_bounds_columns(): void
    {
        $b = $this->normalizedBlock([
            'type' => 'gallery',
            'media_ids' => [3, 3, '5', 0, -2, 7],
            'columns' => 4,
        ]);
        $this->assertSame([3, 5, 7], $b['media_ids']); // dédupe, >0, cast int
        $this->assertSame(4, $b['columns']);

        // colonnes hors [2,3,4] → 3
        $this->assertSame(3, $this->normalizedBlock(['type' => 'gallery', 'media_ids' => [1], 'columns' => 9])['columns']);
    }

    public function test_separator_supports_height_variants(): void
    {
        $this->assertSame('space-lg', $this->normalizedBlock(['type' => 'separator', 'variant' => 'space-lg'])['variant']);
        $this->assertSame('line', $this->normalizedBlock(['type' => 'separator', 'variant' => 'zigzag'])['variant']);
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

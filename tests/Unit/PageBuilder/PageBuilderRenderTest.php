<?php

declare(strict_types=1);

namespace Tests\Unit\PageBuilder;

use Pko\PageBuilder\View\Components\Render;
use Tests\TestCase;

final class PageBuilderRenderTest extends TestCase
{
    /**
     * @param  array<int, array{q?: string, a?: string}>  $items
     */
    private function faqFor(array $items): ?string
    {
        return (new Render(content: [
            'sections' => [['layout' => '1col', 'columns' => [['blocks' => [
                ['type' => 'accordion', 'items' => $items],
            ]]]]],
        ]))->faqJsonLd();
    }

    public function test_accordion_generates_faqpage_json_ld(): void
    {
        $json = $this->faqFor([
            ['q' => 'Délai de livraison ?', 'a' => '48h'],
            ['q' => 'Retour possible ?', 'a' => 'Sous 14 jours'],
        ]);

        $this->assertNotNull($json);
        $data = json_decode($json, true);
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(2, $data['mainEntity']);
        $this->assertSame('Question', $data['mainEntity'][0]['@type']);
        $this->assertSame('Délai de livraison ?', $data['mainEntity'][0]['name']);
        $this->assertSame('48h', $data['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_incomplete_items_are_excluded(): void
    {
        $json = $this->faqFor([
            ['q' => 'Question sans réponse', 'a' => ''],
            ['q' => '', 'a' => 'Réponse sans question'],
        ]);

        // Aucune paire complète → pas de JSON-LD du tout.
        $this->assertNull($json);
    }

    public function test_no_accordion_yields_no_json_ld(): void
    {
        $render = new Render(content: [
            'sections' => [['layout' => '1col', 'columns' => [['blocks' => [
                ['type' => 'text', 'html' => '<p>Hello</p>'],
            ]]]]],
        ]);

        $this->assertNull($render->faqJsonLd());
    }

    public function test_script_tag_cannot_break_out(): void
    {
        // Même si un tag script passait la normalisation, JSON_HEX_TAG neutralise < et >.
        $json = $this->faqFor([
            ['q' => 'Test </script><script>alert(1)</script>', 'a' => 'Réponse'],
        ]);

        $this->assertNotNull($json);
        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString('>', $json);
    }
}

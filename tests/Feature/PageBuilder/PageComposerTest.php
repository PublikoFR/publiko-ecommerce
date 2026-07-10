<?php

declare(strict_types=1);

namespace Tests\Feature\PageBuilder;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Models\PostType;
use Pko\StorefrontCms\Services\PageComposer;
use Tests\TestCase;

/**
 * Couvre le service de composition IA (utilisé par le tool MCP create_cms_page).
 */
class PageComposerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function composer(): PageComposer
    {
        return app(PageComposer::class);
    }

    private function anyTypeHandle(): string
    {
        return (string) (PostType::query()->orderBy('sort_order')->value('handle')
            ?? PostType::create(['label' => 'Page', 'handle' => 'page', 'url_segment' => 'page', 'sort_order' => 1])->handle);
    }

    public function test_creates_draft_page_with_normalized_content(): void
    {
        $handle = $this->anyTypeHandle();

        $post = $this->composer()->create([
            'post_type' => $handle,
            'title' => 'À propos',
            'content' => [
                'sections' => [[
                    'layout' => '1col',
                    'columns' => [['blocks' => [
                        ['type' => 'title', 'level' => 'h2', 'text' => 'Notre mission'],
                        ['type' => 'callout', 'variant' => 'info', 'text' => 'Bienvenue'],
                        ['type' => 'carousel', 'foo' => 'bar'], // inconnu → droppé
                    ]]],
                ]],
            ],
        ]);

        $this->assertInstanceOf(Post::class, $post->refresh());
        $this->assertSame('draft', $post->status);
        $this->assertSame('a-propos', $post->slug);
        // H1 par défaut = titre
        $this->assertSame('À propos', $post->content['heading']);
        // bloc inconnu retiré → 2 blocs
        $this->assertCount(2, $post->content['sections'][0]['columns'][0]['blocks']);
    }

    public function test_heading_can_be_overridden_for_seo(): void
    {
        $post = $this->composer()->create([
            'post_type' => $this->anyTypeHandle(),
            'title' => 'Contact',
            'heading' => 'Envoyez-nous un message',
            'content' => ['sections' => []],
        ]);

        $this->assertSame('Contact', $post->title);
        $this->assertSame('Envoyez-nous un message', $post->content['heading']);
    }

    public function test_slug_is_made_unique(): void
    {
        $handle = $this->anyTypeHandle();
        $a = $this->composer()->create(['post_type' => $handle, 'title' => 'Guide', 'content' => ['sections' => []]]);
        $b = $this->composer()->create(['post_type' => $handle, 'title' => 'Guide', 'content' => ['sections' => []]]);

        $this->assertSame('guide', $a->slug);
        $this->assertSame('guide-2', $b->slug);
    }

    public function test_published_status_sets_date(): void
    {
        $post = $this->composer()->create([
            'post_type' => $this->anyTypeHandle(),
            'title' => 'Promo',
            'status' => 'published',
            'content' => ['sections' => []],
        ]);

        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_unknown_post_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->composer()->create(['post_type' => 'inexistant', 'title' => 'X', 'content' => ['sections' => []]]);
    }

    public function test_missing_title_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->composer()->create(['post_type' => $this->anyTypeHandle(), 'title' => '  ', 'content' => ['sections' => []]]);
    }
}

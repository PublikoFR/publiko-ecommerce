<?php

declare(strict_types=1);

namespace Tests\Feature\PageBuilder;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Pko\PageBuilder\Livewire\PageBuilder;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Models\PostType;
use Tests\TestCase;

/**
 * Vérifie l'éditeur unifié (mode $withMeta) : hydratation, titre H1 on-page
 * dissociable du nom en base, sauvegarde/publication, unicité du slug et
 * insertion des nouveaux types de blocs.
 */
class PageBuilderEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function postType(): PostType
    {
        return PostType::query()->first()
            ?? PostType::create(['label' => 'Page', 'handle' => 'page', 'url_segment' => 'page', 'sort_order' => 1]);
    }

    private function makePost(array $attrs = []): Post
    {
        return Post::create(array_merge([
            'post_type_id' => $this->postType()->id,
            'title' => 'Contact',
            'slug' => 'pb-test-'.bin2hex(random_bytes(4)),
            'status' => 'draft',
        ], $attrs));
    }

    private function editor(Post $post): Testable
    {
        return Livewire::test(PageBuilder::class, [
            'modelClass' => Post::class,
            'recordId' => $post->id,
            'withMeta' => true,
        ]);
    }

    public function test_mount_defaults_heading_to_title(): void
    {
        $post = $this->makePost(['title' => 'Contact']);

        $this->editor($post)
            ->assertSet('heading', 'Contact')
            ->assertSet('title', 'Contact')
            ->assertSet('withMeta', true);
    }

    public function test_save_persists_heading_metadata_and_blocks(): void
    {
        $post = $this->makePost();

        $this->editor($post)
            ->set('heading', 'Envoyez-nous un message')
            ->set('seoTitle', 'Contact — SEO')
            ->call('addSection', '1col')
            ->call('save')
            ->assertHasNoErrors();

        $post->refresh();
        $this->assertSame('Envoyez-nous un message', $post->content['heading']);
        $this->assertSame('Contact — SEO', $post->seo_title);
        $this->assertCount(1, $post->content['sections']);
    }

    public function test_on_page_heading_can_differ_from_db_title(): void
    {
        $post = $this->makePost(['title' => 'Contact']);

        $this->editor($post)
            ->set('heading', 'Envoyez-nous un message')
            ->set('title', 'Contact')
            ->call('save')
            ->assertHasNoErrors();

        $post->refresh();
        $this->assertSame('Contact', $post->title); // nom en base
        $this->assertSame('Envoyez-nous un message', data_get($post->content, 'heading')); // H1 on-page
    }

    public function test_publish_sets_status_and_date(): void
    {
        $post = $this->makePost(['status' => 'draft', 'published_at' => null]);

        $this->editor($post)->call('publish')->assertHasNoErrors();

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $type = $this->postType();
        Post::create(['post_type_id' => $type->id, 'title' => 'A', 'slug' => 'pb-dup-slug', 'status' => 'draft']);
        $b = $this->makePost(['title' => 'B']);

        $this->editor($b)
            ->set('slug', 'pb-dup-slug')
            ->call('save')
            ->assertHasErrors('slug');

        $this->assertNotSame('pb-dup-slug', $b->refresh()->slug);
    }

    public function test_insert_new_block_types(): void
    {
        $post = $this->makePost();

        $sections = $this->editor($post)
            ->call('addSection', '1col')
            ->call('insertBlock', 0, 0, 0, 'quote')
            ->call('insertBlock', 0, 0, 1, 'button')
            ->call('insertBlock', 0, 0, 2, 'separator')
            ->get('sections');

        $blocks = $sections[0]['columns'][0]['blocks'];
        $this->assertSame('quote', $blocks[0]['type']);
        $this->assertSame('button', $blocks[1]['type']);
        $this->assertSame('separator', $blocks[2]['type']);
    }

    public function test_brand_page_mode_has_no_meta(): void
    {
        // Sans $withMeta : pas de métadonnées ni de H1 par défaut forcé.
        $post = $this->makePost(['title' => 'Contact']);

        Livewire::test(PageBuilder::class, [
            'modelClass' => Post::class,
            'recordId' => $post->id,
        ])
            ->assertSet('withMeta', false)
            ->assertSet('heading', ''); // pas de fallback titre hors mode méta
    }
}

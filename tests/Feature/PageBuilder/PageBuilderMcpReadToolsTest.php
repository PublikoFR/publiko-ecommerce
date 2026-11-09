<?php

declare(strict_types=1);

namespace Tests\Feature\PageBuilder;

use App\Mcp\Servers\PageBuilderMcpServer;
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\ListPagesTool;
use App\Mcp\Tools\UpdatePageTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Models\PostType;
use Pko\StorefrontCms\Services\PageComposer;
use Tests\TestCase;

/**
 * Tools MCP de lecture : list_cms_pages (filtres + pagination, brouillons
 * compris) et get_cms_page (contenu complet par id ou slug).
 */
class PageBuilderMcpReadToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PostType::query()->firstOrCreate(['handle' => 'page'], ['label' => 'Page', 'url_segment' => 'page', 'sort_order' => 1]);
        PostType::query()->firstOrCreate(['handle' => 'article'], ['label' => 'Article', 'url_segment' => 'blog', 'sort_order' => 2]);
    }

    private function make(string $type, string $title, string $status = 'draft', ?string $slug = null): Post
    {
        return app(PageComposer::class)->create(array_filter([
            'post_type' => $type,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'content' => [
                'heading' => $title.' H1',
                'sections' => [[
                    'layout' => '1col',
                    'columns' => [['blocks' => [['type' => 'text', 'html' => '<p>Corps '.$title.'</p>']]]],
                ]],
            ],
        ]));
    }

    public function test_lists_drafts_and_published_contents(): void
    {
        $this->make('page', 'Mentions légales', 'published');
        $this->make('page', 'Page brouillon');
        $this->make('article', 'Article publié', 'published');

        PageBuilderMcpServer::tool(ListPagesTool::class)
            ->assertOk()
            ->assertSee(['Mentions légales', 'Page brouillon', 'Article publié']);
    }

    public function test_filters_by_type_status_and_search(): void
    {
        $this->make('page', 'Mentions légales', 'published');
        $this->make('page', 'Page brouillon');
        $this->make('article', 'Article publié', 'published');

        PageBuilderMcpServer::tool(ListPagesTool::class, ['post_type' => 'page', 'status' => 'draft'])
            ->assertOk()
            ->assertSee('Page brouillon')
            ->assertDontSee(['Mentions légales', 'Article publié']);

        PageBuilderMcpServer::tool(ListPagesTool::class, ['search' => 'mentions'])
            ->assertOk()
            ->assertSee('Mentions légales')
            ->assertDontSee(['Page brouillon', 'Article publié']);
    }

    public function test_paginates_results(): void
    {
        foreach (range(1, 3) as $i) {
            $this->make('page', "Page numéro {$i}");
        }

        PageBuilderMcpServer::tool(ListPagesTool::class, ['per_page' => 2, 'page' => 2])
            ->assertOk()
            ->assertSee(['"total":3', '"page":2', '"has_more":false']);
    }

    public function test_reads_full_content_by_id_and_by_slug(): void
    {
        $post = $this->make('page', 'Qui sommes-nous', 'draft', 'qui-sommes-nous');

        PageBuilderMcpServer::tool(GetPageTool::class, ['id' => $post->getKey()])
            ->assertOk()
            ->assertSee(['Qui sommes-nous H1', 'Corps Qui sommes-nous', '"sections"']);

        PageBuilderMcpServer::tool(GetPageTool::class, ['slug' => 'qui-sommes-nous'])
            ->assertOk()
            ->assertSee('Corps Qui sommes-nous');
    }

    public function test_ambiguous_slug_requires_post_type(): void
    {
        $this->make('page', 'Actualités', 'draft', 'actualites');
        $this->make('article', 'Actualités', 'draft', 'actualites');

        PageBuilderMcpServer::tool(GetPageTool::class, ['slug' => 'actualites'])
            ->assertHasErrors();

        PageBuilderMcpServer::tool(GetPageTool::class, ['slug' => 'actualites', 'post_type' => 'article'])
            ->assertOk()
            ->assertSee('"post_type":"article"');
    }

    public function test_unknown_content_returns_an_error(): void
    {
        PageBuilderMcpServer::tool(GetPageTool::class, ['id' => 999999])->assertHasErrors();
        PageBuilderMcpServer::tool(GetPageTool::class, [])->assertHasErrors();
    }

    public function test_create_then_update_tools_return_the_shared_summary(): void
    {
        PageBuilderMcpServer::tool(CreatePageTool::class, ['post_type' => 'page', 'title' => 'Nouvelle page'])
            ->assertOk()
            ->assertSee(['"status":"draft"', '"blocks_count":0', '"admin_edit_url"']);

        $post = Post::query()->where('title', 'Nouvelle page')->firstOrFail();

        PageBuilderMcpServer::tool(UpdatePageTool::class, ['id' => $post->getKey(), 'status' => 'published'])
            ->assertOk()
            ->assertSee('"status":"published"');
    }
}

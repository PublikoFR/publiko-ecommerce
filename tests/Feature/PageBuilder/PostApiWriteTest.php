<?php

declare(strict_types=1);

namespace Tests\Feature\PageBuilder;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Pko\StorefrontCms\Models\Post;
use Pko\StorefrontCms\Models\PostType;
use Tests\TestCase;

/**
 * Écriture de pages via API Platform (même surface /api que le reste du dashboard,
 * gated auth:staff). Créer / modifier / publier une page = POST/PATCH /api/posts.
 */
class PostApiWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function actingAsStaff(): void
    {
        $staff = Staff::create([
            'first_name' => 'Api',
            'last_name' => 'Bot',
            'email' => 'api-bot@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');
    }

    private function typeId(): int
    {
        return (int) (PostType::query()->orderBy('sort_order')->value('id')
            ?? PostType::create(['label' => 'Page', 'handle' => 'page', 'url_segment' => 'page', 'sort_order' => 1])->id);
    }

    public function test_guest_cannot_create_page(): void
    {
        $this->postJson('/api/posts', ['title' => 'X'])->assertStatus(401);
    }

    public function test_staff_creates_page_as_draft(): void
    {
        $this->actingAsStaff();

        $response = $this->postJson('/api/posts', [
            'post_type' => $this->typeId(), // scalaire (handle ou id) — friendly IA
            'title' => 'À propos',
            'content' => [
                'heading' => 'Qui sommes-nous',
                'sections' => [[
                    'layout' => '1col',
                    'columns' => [['blocks' => [
                        ['type' => 'title', 'level' => 'h2', 'text' => 'Notre histoire'],
                        ['type' => 'carousel', 'x' => 1], // inconnu → droppé par normalize
                    ]]],
                ]],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertSuccessful();

        $post = Post::query()->where('title', 'À propos')->firstOrFail();
        $this->assertSame('draft', $post->status);
        $this->assertSame('Qui sommes-nous', $post->content['heading']);
        $this->assertCount(1, $post->content['sections'][0]['columns'][0]['blocks']); // bloc inconnu retiré
    }

    public function test_staff_can_patch_and_publish_a_draft(): void
    {
        $this->actingAsStaff();
        $draft = Post::create([
            'post_type_id' => $this->typeId(),
            'title' => 'Brouillon',
            'slug' => 'brouillon-api',
            'status' => 'draft',
            'content' => ['sections' => []],
        ]);

        // Le global scope published-only ne doit PAS masquer le draft pour le staff.
        $response = $this->patchJson('/api/posts/'.$draft->getKey(), [
            'status' => 'published',
        ], ['Accept' => 'application/json', 'Content-Type' => 'application/merge-patch+json']);

        $response->assertSuccessful();
        $draft->refresh();
        $this->assertSame('published', $draft->status);
        $this->assertNotNull($draft->published_at);
    }

    public function test_create_without_title_is_rejected(): void
    {
        $this->actingAsStaff();

        $this->postJson('/api/posts', [
            'postTypeId' => $this->typeId(),
            'content' => ['sections' => []],
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }
}

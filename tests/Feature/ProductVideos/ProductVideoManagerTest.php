<?php

declare(strict_types=1);

namespace Tests\Feature\ProductVideos;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Lunar\Models\Product;
use Pko\ProductVideos\Enums\VideoProvider;
use Pko\ProductVideos\Models\ProductVideo;
use Pko\ProductVideos\Services\ProductVideoManager;
use Tests\TestCase;

class ProductVideoManagerTest extends TestCase
{
    use RefreshDatabase;

    private ProductVideoManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->manager = app(ProductVideoManager::class);
    }

    public function test_add_persists_provider_and_video_id(): void
    {
        $product = Product::first();

        $video = $this->manager->add($product, 'https://youtu.be/dQw4w9WgXcQ', 'Teaser');

        $this->assertDatabaseHas('pko_product_videos', [
            'id' => $video->id,
            'product_id' => $product->id,
            'provider' => VideoProvider::YouTube->value,
            'provider_video_id' => 'dQw4w9WgXcQ',
            'title' => 'Teaser',
            'sort_order' => 1,
        ]);
    }

    public function test_add_if_not_exists_is_idempotent(): void
    {
        $product = Product::first();
        $url = 'https://vimeo.com/123456';

        $first = $this->manager->addIfNotExists($product, $url);
        $second = $this->manager->addIfNotExists($product, $url);

        $this->assertNotNull($first);
        $this->assertNull($second, 'Second insertion must return null (already exists)');
        $this->assertSame(1, ProductVideo::query()->where('product_id', $product->id)->count());
    }

    public function test_exists_returns_true_after_add(): void
    {
        $product = Product::first();
        $this->assertFalse($this->manager->exists($product, 'https://dai.ly/x7tgad0'));

        $this->manager->add($product, 'https://dai.ly/x7tgad0');

        $this->assertTrue($this->manager->exists($product, 'https://dai.ly/x7tgad0'));
    }

    public function test_sync_reports_added_skipped_and_errors(): void
    {
        $product = Product::first();
        $this->manager->add($product, 'https://youtu.be/abcDEF12345');

        $result = $this->manager->sync($product, [
            'https://youtu.be/abcDEF12345',            // skipped (already exists)
            'https://vimeo.com/999',                    // added
            'https://cdn.example.com/clip.mp4',         // added
            'https://example.com/unknown',              // error (unsupported)
            '',                                          // ignored
        ]);

        $this->assertSame(['added' => 2, 'skipped' => 1, 'errors' => 1], $result);
        $this->assertSame(3, ProductVideo::query()->where('product_id', $product->id)->count());
    }

    public function test_reorder_rewrites_sort_order(): void
    {
        $product = Product::first();
        $a = $this->manager->add($product, 'https://youtu.be/aaaaaaaaaaa');
        $b = $this->manager->add($product, 'https://youtu.be/bbbbbbbbbbb');
        $c = $this->manager->add($product, 'https://youtu.be/ccccccccccc');

        $this->manager->reorder($product, [$c->id, $a->id, $b->id]);

        $this->assertSame(1, (int) $c->refresh()->sort_order);
        $this->assertSame(2, (int) $a->refresh()->sort_order);
        $this->assertSame(3, (int) $b->refresh()->sort_order);
    }

    public function test_add_persists_vimeo_thumbnail_via_oembed(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response([
                'thumbnail_url' => 'https://i.vimeocdn.com/video/abc.jpg',
            ]),
        ]);
        $product = Product::first();

        $video = $this->manager->add($product, 'https://vimeo.com/524933864');

        $this->assertSame('https://i.vimeocdn.com/video/abc.jpg', $video->thumbnail_url);
    }

    public function test_add_persists_youtube_thumbnail_from_pattern(): void
    {
        Http::fake(); // fail loud if an oEmbed call sneaks in
        $product = Product::first();

        $video = $this->manager->add($product, 'https://youtu.be/dQw4w9WgXcQ');

        $this->assertSame(
            'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            $video->thumbnail_url,
        );
        Http::assertNothingSent();
    }

    public function test_delete_removes_the_row(): void
    {
        $product = Product::first();
        $video = $this->manager->add($product, 'https://youtu.be/zzzzzzzzzzz');

        $this->manager->delete($video);

        $this->assertDatabaseMissing('pko_product_videos', ['id' => $video->id]);
    }
}

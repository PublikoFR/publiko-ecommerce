<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\LunarMediaCore\Services\MediaLibraryImporter;
use Pko\ProductDocuments\Models\ProductDocument;
use Pko\ProductVideos\Models\ProductVideo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Anti-doublon des médias à l'import : images, documents, vidéos.
 * Aucun appel réseau réel (Http::fake + preventStrayRequests).
 */
class WriterMediaDedupTest extends TestCase
{
    use RefreshDatabase;

    private ImportJob $job;

    private int $row = 1;

    private string $pngA;

    private string $pngB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('media-library.disk_name'));
        Http::preventStrayRequests();

        ProductType::firstOrCreate(['name' => 'Test Type']);
        TaxClass::firstOrCreate(['name' => 'Standard'], ['default' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'exchange_rate' => 1.0, 'default' => true, 'enabled' => true]);
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);

        $this->job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);

        $this->pngA = $this->png(4, 0xFF0000);
        $this->pngB = $this->png(6, 0x00FF00);
    }

    private function png(int $size, int $color): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** @param  array<string, mixed>  $data */
    private function write(array $data, ?LunarProductWriter $writer = null): StagingRecord
    {
        $record = StagingRecord::create([
            'import_job_id' => $this->job->id,
            'row_number' => $this->row++,
            'data' => $data,
            'status' => StagingStatus::Pending,
        ]);

        ($writer ?? (new LunarProductWriter)->setJobId($this->job->id))->write($record);

        return $record->refresh();
    }

    private function product(string $sku): Product
    {
        return ProductVariant::where('sku', $sku)->firstOrFail()->product;
    }

    /** @return array<int, int> */
    private function gallery(string $sku): array
    {
        $product = $this->product($sku);

        return DB::table('pko_mediables')
            ->where('mediable_type', $product::class)
            ->where('mediable_id', $product->id)
            ->where('mediagroup', 'product')
            ->orderBy('position')
            ->pluck('media_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function test_same_url_on_two_products_gives_one_media_and_no_second_download(): void
    {
        Http::fake(['cdn.test/*' => Http::response($this->pngA, 200, ['Content-Type' => 'image/png'])]);

        $this->write(['reference' => 'IMG-1', 'name' => 'Produit 1', 'images' => 'https://cdn.test/a.png']);
        $this->write(['reference' => 'IMG-2', 'name' => 'Produit 2', 'images' => 'https://cdn.test/a.png']);

        $this->assertSame(1, Media::count());
        $this->assertSame($this->gallery('IMG-1'), $this->gallery('IMG-2'));
        Http::assertSentCount(1);
    }

    public function test_reimport_does_not_duplicate_links_nor_download_again(): void
    {
        Http::fake(['cdn.test/*' => Http::response($this->pngA, 200)]);

        $this->write(['reference' => 'IMG-3', 'name' => 'Produit', 'images' => 'https://cdn.test/a.png']);
        $this->write(['reference' => 'IMG-3', 'name' => 'Produit', 'images' => 'https://cdn.test/a.png']);
        $this->write(['reference' => 'IMG-3', 'name' => 'Produit', 'images' => 'https://cdn.test/a.png']);

        $this->assertSame(1, Media::count());
        $this->assertCount(1, $this->gallery('IMG-3'));
        Http::assertSentCount(1);
    }

    public function test_url_variants_and_repeats_in_a_row_are_one_image(): void
    {
        Http::fake(['*' => Http::response($this->pngA, 200)]);

        $this->write([
            'reference' => 'IMG-4', 'name' => 'Produit',
            'images' => 'https://cdn.test/a.png,https://cdn.test/a.png, http://CDN.test/a.png ,https://www.cdn.test/a.png#zoom',
        ]);

        $this->assertSame(1, Media::count());
        $this->assertCount(1, $this->gallery('IMG-4'));
        Http::assertSentCount(1);
    }

    public function test_same_file_under_another_url_is_reused_and_remembered_as_alias(): void
    {
        Http::fake(['*' => Http::response($this->pngA, 200)]);

        $this->write(['reference' => 'IMG-5', 'name' => 'Produit', 'images' => 'https://cdn.test/a.png']);
        $this->write(['reference' => 'IMG-6', 'name' => 'Autre', 'images' => 'https://mirror.test/photo-hd.png']);

        // Même binaire : un seul média, lié aux deux produits.
        $this->assertSame(1, Media::count());
        $this->assertSame($this->gallery('IMG-5'), $this->gallery('IMG-6'));
        Http::assertSentCount(2);

        // L'alias est mémorisé : un nouvel import (nouveau writer) ne retélécharge pas.
        $this->write(['reference' => 'IMG-6', 'name' => 'Autre', 'images' => 'https://mirror.test/photo-hd.png']);
        Http::assertSentCount(2);
        $this->assertSame(1, Media::count());
    }

    public function test_manually_uploaded_file_is_recognised_by_its_content(): void
    {
        // Média déposé à la main dans la médiathèque : ni source_url ni sha1.
        $importer = app(MediaLibraryImporter::class);
        $folder = $importer->resolveFolder('products');
        $tmp = tempnam(sys_get_temp_dir(), 'pko-test-');
        file_put_contents($tmp, $this->pngA);
        $manual = $folder->addMedia($tmp)->usingFileName('manuel.png')->toMediaCollection('products');

        Http::fake(['*' => Http::response($this->pngA, 200)]);
        $this->write(['reference' => 'IMG-7', 'name' => 'Produit', 'images' => 'https://cdn.test/a.png']);

        $this->assertSame(1, Media::count());
        $this->assertSame([(int) $manual->id], $this->gallery('IMG-7'));
        $this->assertNotNull($manual->fresh()->getCustomProperty('sha1'));
    }

    public function test_different_files_stay_distinct_and_keep_order(): void
    {
        Http::fake([
            'cdn.test/a.png' => Http::response($this->pngA, 200),
            'cdn.test/b.png' => Http::response($this->pngB, 200),
        ]);

        $this->write(['reference' => 'IMG-8', 'name' => 'Produit', 'images' => 'https://cdn.test/a.png,https://cdn.test/b.png']);

        $this->assertSame(2, Media::count());
        $this->assertCount(2, $this->gallery('IMG-8'));
    }

    public function test_replace_images_swaps_the_gallery_and_default_appends(): void
    {
        Http::fake([
            'cdn.test/a.png' => Http::response($this->pngA, 200),
            'cdn.test/b.png' => Http::response($this->pngB, 200),
        ]);

        $this->write(['reference' => 'IMG-9', 'name' => 'Produit', 'images' => 'https://cdn.test/a.png']);
        [$a] = $this->gallery('IMG-9');

        // Défaut : ajout.
        $this->write(['reference' => 'IMG-9', 'name' => 'Produit', 'images' => 'https://cdn.test/b.png']);
        $this->assertCount(2, $this->gallery('IMG-9'));

        // Remplacement demandé (alias PrestaShop) : la galerie = la ligne.
        $this->write(['reference' => 'IMG-9', 'name' => 'Produit', 'images' => 'https://cdn.test/b.png', 'delete_existing_images' => '1']);
        $gallery = $this->gallery('IMG-9');
        $this->assertCount(1, $gallery);
        $this->assertNotContains($a, $gallery);

        // Les fichiers restent en médiathèque.
        $this->assertSame(2, Media::count());
    }

    public function test_replace_never_empties_the_gallery_when_downloads_fail(): void
    {
        Http::fake([
            'cdn.test/a.png' => Http::response($this->pngA, 200),
            'cdn.test/ko.png' => Http::response('', 404),
        ]);

        $this->write(['reference' => 'IMG-10', 'name' => 'Produit', 'images' => 'https://cdn.test/a.png']);
        $this->write(['reference' => 'IMG-10', 'name' => 'Produit', 'images' => 'https://cdn.test/ko.png', 'replace_images' => '1']);

        $this->assertCount(1, $this->gallery('IMG-10'));
    }

    public function test_failing_url_is_tried_once_per_import_and_logged_with_reason(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $writer = (new LunarProductWriter)->setJobId($this->job->id);
        $this->write(['reference' => 'IMG-11', 'name' => 'Produit 1', 'images' => 'https://cdn.test/ko.png'], $writer);
        $this->write(['reference' => 'IMG-12', 'name' => 'Produit 2', 'images' => 'https://cdn.test/ko.png'], $writer);

        Http::assertSentCount(1);
        $this->assertSame(0, Media::count());
        $this->assertSame(2, ImportLog::where('message', 'like', 'Image non importée (HTTP 404)%')->count());
    }

    public function test_documents_are_attached_once_and_never_reordered(): void
    {
        $pdf = '%PDF-1.4 test '.str_repeat('x', 64);
        Http::fake(['*' => Http::response($pdf, 200)]);

        $documents = json_encode([
            ['type' => 'NOTICE', 'url' => 'https://cdn.test/notice.pdf', 'name' => 'Notice'],
            ['type' => 'NOTICE', 'url' => 'https://cdn.test/notice.pdf', 'name' => 'Notice (doublon)'],
        ]);

        $this->write(['reference' => 'DOC-1', 'name' => 'Produit', 'attachments' => $documents]);
        $this->write(['reference' => 'DOC-1', 'name' => 'Produit', 'attachments' => $documents]);

        $product = $this->product('DOC-1');
        $this->assertSame(1, ProductDocument::where('product_id', $product->id)->count());
        $this->assertSame(1, Media::count());
        Http::assertSentCount(1);
    }

    public function test_same_video_under_another_url_form_is_not_added_twice(): void
    {
        Http::fake(['*' => Http::response('{}', 200)]);

        $this->write(['reference' => 'VID-1', 'name' => 'Produit', 'videos' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
        $this->write(['reference' => 'VID-1', 'name' => 'Produit', 'videos' => 'https://youtu.be/dQw4w9WgXcQ,https://www.youtube.com/embed/dQw4w9WgXcQ']);

        $this->assertSame(1, ProductVideo::where('product_id', $this->product('VID-1')->id)->count());
    }
}

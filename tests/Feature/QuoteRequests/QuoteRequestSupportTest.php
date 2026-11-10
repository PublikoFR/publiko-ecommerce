<?php

declare(strict_types=1);

namespace Tests\Feature\QuoteRequests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Lunar\Models\ProductVariant;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Mail\QuoteRequestAdminMail;
use Pko\QuoteRequests\Mail\QuoteRequestReceivedMail;
use Pko\QuoteRequests\Models\QuoteRequest;
use Pko\QuoteRequests\Support\FileSignature;
use Pko\QuoteRequests\Support\LegacyQuoteLinks;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;
use ZipArchive;

class QuoteRequestSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_request_url_builds_mode_and_context(): void
    {
        $this->assertSame('/demande-de-devis', quote_request_url());
        $this->assertSame('/demande-de-devis?mode=part_photo', quote_request_url('part_photo'));
        $this->assertSame(
            '/demande-de-devis?mode=part_photo&product=42&ref=MT-10&from=%2Fproduits%2Fx',
            quote_request_url('part_photo', ['product' => 42, 'ref' => 'MT-10', 'from' => '/produits/x']),
        );
        // Ancien mode « projet » (fusionné) : demande générale, sans paramètre de mode.
        $this->assertSame(
            '/demande-de-devis?product=42',
            quote_request_url('project', ['product' => 42]),
        );
        // URL d'origine externe : écartée, jamais propagée.
        $this->assertSame('/demande-de-devis', quote_request_url('general', ['from' => 'https://evil.example/']));
        // Mode inconnu : demande générale.
        $this->assertSame('/demande-de-devis', quote_request_url('nope'));
    }

    public function test_quote_request_url_accepts_a_variant_model(): void
    {
        $variant = new ProductVariant;
        $variant->id = 7;

        $this->assertSame('/demande-de-devis?product=7', quote_request_url('general', ['product' => $variant]));
    }

    public function test_file_signature_accepts_expected_formats_only(): void
    {
        $xlsx = tempnam(sys_get_temp_dir(), 'qr');
        $zip = new ZipArchive;
        $zip->open($xlsx, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        $zip->close();

        $plainZip = tempnam(sys_get_temp_dir(), 'qr');
        $zip->open($plainZip, ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'x');
        $zip->close();

        $cases = [
            ['photo.png', "\x89PNG\r\n\x1A\n0000", 'png'],
            ['photo.webp', 'RIFF0000WEBPVP8 ', 'webp'],
            ['plan.PDF', "%PDF-1.7\n", 'pdf'],
            ['plan.dwg', 'AC1032'.str_repeat("\x00", 10), 'dwg'],
            ['liste.csv', "ref;qte\nMT-10;2\n", 'csv'],
            ['plan.pdf', 'MZ'.str_repeat("\x00", 20), null],
            ['photo.jpg', "%PDF-1.4\n", null],
            ['script.csv', "ref\x00\x01", null],
            ['page.html', '<html></html>', null],
        ];

        foreach ($cases as [$name, $content, $expected]) {
            $file = UploadedFile::fake()->createWithContent($name, $content);
            $this->assertSame($expected, FileSignature::detect($file->getRealPath(), $name), $name);
        }

        $this->assertSame('xlsx', FileSignature::detect($xlsx, 'liste.xlsx'));
        $this->assertNull(FileSignature::detect($plainZip, 'liste.xlsx'));
    }

    public function test_legacy_links_are_rewritten_only_for_old_defaults(): void
    {
        $this->assertSame('/demande-de-devis', LegacyQuoteLinks::navQuoteUrl('/contact'));
        $this->assertSame('/devis-perso', LegacyQuoteLinks::navQuoteUrl('/devis-perso'));

        $columns = LegacyQuoteLinks::footerColumns([
            ['title' => 'Accompagnement', 'links' => [
                ['label' => 'Demander un devis', 'href' => '/contact'],
                ['label' => 'Identifier une pièce', 'href' => '/pages/identifier-une-piece'],
                ['label' => 'Nous contacter', 'href' => '/contact'],
                ['label' => 'Demander un devis', 'href' => '/pages/mon-devis'],
            ]],
        ]);

        $this->assertSame([
            ['label' => 'Demander un devis', 'href' => '/demande-de-devis'],
            ['label' => 'Identifier une pièce', 'href' => '/demande-de-devis?mode=part_photo'],
            ['label' => 'Nous contacter', 'href' => '/contact'],
            ['label' => 'Demander un devis', 'href' => '/pages/mon-devis'],
        ], $columns[0]['links']);

        $services = LegacyQuoteLinks::links([
            ['title' => "Identification d'une pièce", 'href' => '/pages/identifier-une-piece'],
            ['title' => 'Devis pour un projet', 'href' => '/contact'],
        ], 'title');

        $this->assertSame('/demande-de-devis?mode=part_photo', $services[0]['href']);
        $this->assertSame('/demande-de-devis?mode=project', $services[1]['href']);
    }

    public function test_data_migration_rewrites_overridden_settings(): void
    {
        Setting::set('nav.quote_url', '/contact');
        Setting::set('home.services', [
            ['icon' => 'camera', 'title' => "Identification d'une pièce", 'subtitle' => '', 'href' => '/pages/identifier-une-piece'],
            ['icon' => 'clipboard-pen', 'title' => 'Devis pour un projet', 'subtitle' => '', 'href' => '/contact'],
        ]);
        Setting::set('topbar.links', [['label' => 'Nous contacter', 'href' => '/contact']]);

        $migration = require base_path('packages/pko/quote-requests/database/migrations/2026_11_09_100100_point_legacy_quote_links_to_quote_request_form.php');
        $migration->up();

        $this->assertSame('/demande-de-devis', Setting::get('nav.quote_url'));
        $this->assertSame('/demande-de-devis?mode=part_photo', Setting::get('home.services')[0]['href']);
        $this->assertSame('/demande-de-devis?mode=project', Setting::get('home.services')[1]['href']);
        $this->assertSame('/contact', Setting::get('topbar.links')[0]['href']);
    }

    public function test_both_mails_render_with_their_context(): void
    {
        $request = QuoteRequest::create([
            'mode' => QuoteRequestMode::PartPhoto,
            'name' => 'Camille <b>Martin</b>',
            'email' => 'camille@example.test',
            'message' => 'Plaque illisible.',
            'reference' => 'MT-10',
            'lines' => [['label' => 'MT-10', 'quantity' => 2]],
        ]);

        $admin = new QuoteRequestAdminMail($request);
        $this->assertTrue($admin->shouldSend());
        $html = $admin->render();
        $this->assertStringContainsString($request->number(), $html);
        $this->assertStringContainsString('Plaque illisible.', $html);
        // Plus de bloc « Produits / quantités » dans le mail équipe.
        $this->assertStringNotContainsString('Produits / quantités', $html);
        $this->assertStringContainsString('/quote-requests/'.$request->id, $html);
        $this->assertStringNotContainsString('<b>Martin</b>', $html);
        $this->assertTrue($admin->hasReplyTo('camille@example.test'));

        $customer = new QuoteRequestReceivedMail($request);
        $this->assertTrue($customer->shouldSend());
        $this->assertStringContainsString($request->number(), $customer->render());
    }

    public function test_storefront_defaults_point_to_the_quote_form(): void
    {
        $config = require base_path('packages/pko/storefront/config/storefront.php');

        $this->assertSame('/demande-de-devis', $config['nav']['quote_url']);
        $hrefs = array_column($config['home']['services'], 'href');
        $this->assertContains('/demande-de-devis?mode=part_photo', $hrefs);
        $this->assertContains('/demande-de-devis?mode=project', $hrefs);
        $this->assertSame('/demande-de-devis', $config['footer']['columns'][0]['links'][0]['href']);
    }

    public function test_merge_migration_moves_project_requests_and_strips_the_default_lines_block(): void
    {
        $request = QuoteRequest::create([
            'mode' => QuoteRequestMode::General,
            'name' => 'Ancien Projet',
            'email' => 'projet@example.test',
            'message' => 'Rénovation.',
        ]);
        DB::table('pko_quote_requests')->where('id', $request->id)->update(['mode' => 'project']);

        $block = ['type' => 'text', 'html' => '<p><strong>Produits / quantités</strong> : :lines</p>'];
        $edited = ['type' => 'text', 'html' => '<p>Texte retouché : :lines</p>'];
        $content = ['heading' => '', 'sections' => [['layout' => '1col', 'columns' => [['blocks' => [
            ['type' => 'title', 'level' => 'h2', 'text' => 'Nouvelle demande de devis'], $block, $edited,
        ]]]]]];
        DB::table('pko_mail_templates')->updateOrInsert(
            ['key' => 'quote_request.received_admin', 'locale' => 'fr'],
            ['subject' => 'Demande', 'content' => json_encode($content), 'enabled' => true],
        );

        $migration = require base_path('packages/pko/quote-requests/database/migrations/2026_11_10_120000_merge_project_mode_and_drop_lines_from_admin_mail.php');
        $migration->up();

        $this->assertSame('general', DB::table('pko_quote_requests')->where('id', $request->id)->value('mode'));
        $stored = json_decode((string) DB::table('pko_mail_templates')->where('key', 'quote_request.received_admin')->value('content'), true);
        $blocks = $stored['sections'][0]['columns'][0]['blocks'];
        $this->assertCount(2, $blocks);
        $this->assertNotContainsEquals($block, $blocks);
        // Un bloc retouché en admin n'est jamais supprimé.
        $this->assertContainsEquals($edited, $blocks);
    }
}

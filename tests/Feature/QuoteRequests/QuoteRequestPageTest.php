<?php

declare(strict_types=1);

namespace Tests\Feature\QuoteRequests;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Customer;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\Url;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Enums\QuoteRequestStatus;
use Pko\QuoteRequests\Livewire\QuoteRequestPage;
use Pko\QuoteRequests\Mail\QuoteRequestAdminMail;
use Pko\QuoteRequests\Mail\QuoteRequestReceivedMail;
use Pko\QuoteRequests\Models\QuoteRequest;
use Tests\TestCase;

class QuoteRequestPageTest extends TestCase
{
    use RefreshDatabase;

    /** Plus petit JPEG reconnu par sa signature (FF D8 FF). */
    private const JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        RateLimiter::clear('quote-request:127.0.0.1');
        config(['storefront.contact.email' => 'equipe@example.test']);
    }

    public function test_each_mode_renders_its_own_title(): void
    {
        $this->get('/demande-de-devis')->assertOk()
            ->assertSee(QuoteRequestMode::General->title())
            ->assertSee('Envoyer ma demande');

        $this->get('/demande-de-devis?mode=project')->assertOk()
            ->assertSee(QuoteRequestMode::Project->title());

        $this->get('/demande-de-devis?mode=part_photo')->assertOk()
            ->assertSee(QuoteRequestMode::PartPhoto->title())
            ->assertSee('Ajouter des références');
    }

    public function test_unknown_mode_falls_back_to_general(): void
    {
        Livewire::withQueryParams(['mode' => 'nope'])
            ->test(QuoteRequestPage::class)
            ->assertSet('mode', 'general');
    }

    public function test_switching_mode_keeps_the_input(): void
    {
        Livewire::test(QuoteRequestPage::class)
            ->set('name', 'Jean Dupont')
            ->set('email', 'jean@example.test')
            ->set('message', 'Une description assez longue.')
            ->set('lines.0.label', 'MT-10')
            ->call('setMode', 'part_photo')
            ->assertSet('mode', 'part_photo')
            ->assertSee(QuoteRequestMode::PartPhoto->title())
            ->assertSet('name', 'Jean Dupont')
            ->assertSet('message', 'Une description assez longue.')
            ->assertSet('lines.0.label', 'MT-10')
            ->call('setMode', 'project')
            ->assertSet('mode', 'project')
            ->assertSet('email', 'jean@example.test');
    }

    public function test_origin_context_is_resolved_shown_and_stored(): void
    {
        Mail::fake();
        [$product, $variant] = $this->makeProduct('Moteur tubulaire 10 Nm', 'moteur-tubulaire', 'MT-10');

        $component = Livewire::withQueryParams([
            'product' => (string) $variant->id,
            'from' => '/produits/moteur-tubulaire',
        ])->test(QuoteRequestPage::class);

        $component
            ->assertSet('context.product_id', $product->id)
            ->assertSet('context.reference', 'MT-10')
            ->assertSet('context.from', '/produits/moteur-tubulaire')
            ->assertSee('Moteur tubulaire 10 Nm')
            ->assertSee('MT-10')
            ->assertSet('lines.0.label', 'MT-10 — Moteur tubulaire 10 Nm');

        $this->fillValid($component)->call('submit')->assertHasNoErrors();

        $request = QuoteRequest::query()->sole();
        $this->assertSame($product->id, $request->product_id);
        $this->assertSame($variant->id, $request->product_variant_id);
        $this->assertSame('MT-10', $request->reference);
        $this->assertSame('/produits/moteur-tubulaire', $request->from_url);
        $this->assertSame('Moteur tubulaire 10 Nm', $request->productLabel());
    }

    public function test_product_slug_and_explicit_ref_are_accepted(): void
    {
        [$product] = $this->makeProduct('Portail coulissant', 'portail-coulissant', 'PC-1');

        Livewire::withQueryParams(['product' => 'portail-coulissant', 'ref' => 'REF-CLIENT'])
            ->test(QuoteRequestPage::class)
            ->assertSet('context.product_id', $product->id)
            ->assertSet('context.reference', 'REF-CLIENT');
    }

    public function test_external_from_url_is_discarded(): void
    {
        foreach (['https://evil.example/phish', '//evil.example', 'javascript:alert(1)'] as $from) {
            Livewire::withQueryParams(['from' => $from])
                ->test(QuoteRequestPage::class)
                ->assertSet('context.from', null);
        }
    }

    public function test_context_is_locked(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(QuoteRequestPage::class)->set('context.product_id', 999);
    }

    public function test_logged_in_customer_details_are_prefilled(): void
    {
        $user = User::factory()->create(['name' => 'Compte Web', 'email' => 'pro@example.test']);
        $customer = Customer::factory()->create([
            'first_name' => 'Camille',
            'last_name' => 'Martin',
            'company_name' => 'Fermetures du Sud',
            'meta' => ['phone' => '0612345678'],
        ]);
        $customer->users()->attach($user);

        Livewire::actingAs($user)
            ->test(QuoteRequestPage::class)
            ->assertSet('name', 'Camille Martin')
            ->assertSet('email', 'pro@example.test')
            ->assertSet('company', 'Fermetures du Sud')
            ->assertSet('phone', '0612345678');
    }

    public function test_submission_persists_request_files_and_queues_both_mails(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'pro@example.test']);
        $customer = Customer::factory()->create(['company_name' => 'Fermetures du Sud']);
        $customer->users()->attach($user);

        $pdf = UploadedFile::fake()->createWithContent('plan.pdf', "%PDF-1.4\n%fake but signed\n");
        $photo = UploadedFile::fake()->createWithContent('plaque.jpg', self::JPEG);

        Livewire::actingAs($user)
            ->withQueryParams(['mode' => 'project'])
            ->test(QuoteRequestPage::class)
            ->set('name', 'Camille Martin')
            ->set('phone', '0612345678')
            ->set('message', 'Rénovation de 12 volets roulants.')
            ->set('lines', [['label' => 'MT-10', 'quantity' => 12], ['label' => '', 'quantity' => 1]])
            ->set('newAttachments', [$pdf, $photo])
            ->assertHasNoErrors()
            ->assertCount('attachments', 2)
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $request = QuoteRequest::query()->with('files')->sole();
        $this->assertSame(QuoteRequestMode::Project, $request->mode);
        $this->assertSame(QuoteRequestStatus::New, $request->status);
        $this->assertSame('pro@example.test', $request->email);
        $this->assertSame($customer->id, $request->customer_id);
        $this->assertSame($user->id, $request->user_id);
        $this->assertSame([['label' => 'MT-10', 'quantity' => 12]], $request->lines);
        $this->assertCount(2, $request->files);

        $pdfFile = $request->files->firstWhere('original_name', 'plan.pdf');
        $this->assertSame('application/pdf', $pdfFile->mime_type);
        Storage::disk('local')->assertExists($pdfFile->path);
        $this->assertStringStartsWith('quote-requests/'.$request->id.'/', $pdfFile->path);

        Mail::assertQueued(QuoteRequestAdminMail::class, fn (QuoteRequestAdminMail $mail): bool => $mail->hasTo('equipe@example.test')
            && $mail->quoteRequest->is($request)
            && $mail->values['attachments_count'] === '2'
            && str_contains($mail->values['admin_url'], '/quote-requests/'.$request->id));
        Mail::assertQueued(QuoteRequestReceivedMail::class, fn (QuoteRequestReceivedMail $mail): bool => $mail->hasTo('pro@example.test')
            && $mail->values['request_number'] === $request->number());
    }

    public function test_fake_pdf_is_rejected_by_its_signature(): void
    {
        $fake = UploadedFile::fake()->createWithContent('plan.pdf', 'MZ'.str_repeat("\x00", 64));
        $renamed = UploadedFile::fake()->createWithContent('liste.xlsx', "%PDF-1.4\n");

        Livewire::test(QuoteRequestPage::class)
            ->set('newAttachments', [$fake, $renamed])
            ->assertHasErrors('attachments')
            ->assertCount('attachments', 0);
    }

    public function test_file_count_and_size_limits_apply(): void
    {
        config(['quote-requests.attachments.max_files' => 1, 'quote-requests.attachments.max_size_kb' => 1]);

        Livewire::test(QuoteRequestPage::class)
            ->set('newAttachments', [UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n".str_repeat('x', 2048))])
            ->assertHasErrors('attachments')
            ->assertCount('attachments', 0)
            ->set('newAttachments', [
                UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\n"),
                UploadedFile::fake()->createWithContent('c.pdf', "%PDF-1.4\n"),
            ])
            ->assertHasErrors('attachments')
            ->assertCount('attachments', 1);
    }

    public function test_part_photo_mode_requires_a_photo(): void
    {
        Mail::fake();

        $component = Livewire::withQueryParams(['mode' => 'part_photo'])->test(QuoteRequestPage::class);
        $this->fillValid($component)->call('submit')->assertHasErrors('attachments');
        $this->assertSame(0, QuoteRequest::query()->count());

        $component->set('newAttachments', [UploadedFile::fake()->createWithContent('piece.jpg', self::JPEG)])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $this->assertSame(QuoteRequestMode::PartPhoto, QuoteRequest::query()->sole()->mode);
    }

    public function test_validation_errors_block_submission(): void
    {
        Mail::fake();

        Livewire::test(QuoteRequestPage::class)
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'message', 'consent']);

        Mail::assertNothingQueued();
        $this->assertSame(0, QuoteRequest::query()->count());
    }

    public function test_lines_alone_are_enough_without_description(): void
    {
        Mail::fake();

        Livewire::test(QuoteRequestPage::class)
            ->set('name', 'Jean Dupont')
            ->set('email', 'jean@example.test')
            ->set('lines.0.label', 'MT-10')
            ->set('lines.0.quantity', 3)
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame('', QuoteRequest::query()->sole()->message);
    }

    public function test_honeypot_simulates_success_without_storing(): void
    {
        Mail::fake();

        $component = Livewire::test(QuoteRequestPage::class);
        $this->fillValid($component)->set('website', 'http://spam.example')
            ->call('submit')
            ->assertSet('sent', true);

        $this->assertSame(0, QuoteRequest::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_rate_limit_blocks_flooding(): void
    {
        Mail::fake();
        config(['quote-requests.rate_limit.max_attempts' => 2]);

        for ($i = 0; $i < 2; $i++) {
            $this->fillValid(Livewire::test(QuoteRequestPage::class))->call('submit')->assertHasNoErrors();
        }

        $this->fillValid(Livewire::test(QuoteRequestPage::class))->call('submit')->assertHasErrors('form');
        $this->assertSame(2, QuoteRequest::query()->count());
    }

    public function test_no_price_is_rendered(): void
    {
        [, $variant] = $this->makeProduct('Moteur tubulaire 10 Nm', 'moteur-tubulaire', 'MT-10');

        // Rendu du composant seul : le bandeau du layout annonce le franco en €.
        Livewire::withQueryParams(['product' => (string) $variant->id])
            ->test(QuoteRequestPage::class)
            ->assertSee('Moteur tubulaire 10 Nm')
            ->assertDontSee('€');
    }

    private function fillValid(mixed $component): mixed
    {
        return $component
            ->set('name', 'Jean Dupont')
            ->set('email', 'jean@example.test')
            ->set('phone', '0612345678')
            ->set('message', 'Bonjour, je souhaite un devis.')
            ->set('consent', true);
    }

    /** @return array{0: Product, 1: ProductVariant} */
    private function makeProduct(string $name, string $slug, string $sku): array
    {
        $product = Product::factory()->create([
            'attribute_data' => collect([
                'name' => new TranslatedText(collect(['fr' => new Text($name)])),
            ]),
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => $sku]);

        $product->urls()->delete();
        Url::create([
            'language_id' => Language::query()->value('id'),
            'element_type' => $product->getMorphClass(),
            'element_id' => $product->id,
            'slug' => $slug,
            'default' => true,
        ]);

        return [$product->refresh(), $variant];
    }
}

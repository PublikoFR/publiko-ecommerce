<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\ContactPage;
use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Enums\QuoteRequestStatus;
use Pko\QuoteRequests\Models\QuoteRequest;
use RuntimeException;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_renders(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('Contactez-nous')
            ->assertSee('Envoyer le message');
    }

    public function test_valid_submission_sends_email_to_shop(): void
    {
        Mail::fake();
        config(['storefront.contact.email' => 'boutique@example.test']);

        Livewire::test(ContactPage::class)
            ->set('name', 'Jean Dupont')
            ->set('email', 'jean@example.test')
            ->set('phone', '0612345678')
            ->set('subject', 'Demande de devis')
            ->set('message', 'Bonjour, je souhaite un devis pour 10 unités.')
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->assertSet('message', '');

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail): bool {
            return $mail->hasTo('boutique@example.test')
                && $mail->senderEmail === 'jean@example.test'
                && $mail->subjectLine === 'Demande de devis';
        });
    }

    public function test_valid_submission_is_stored_for_the_admin_contacts_page(): void
    {
        Mail::fake();

        Livewire::test(ContactPage::class)
            ->set('name', 'Jean Dupont')
            ->set('email', 'jean@example.test')
            ->set('phone', '')
            ->set('subject', 'Horaires')
            ->set('message', 'Bonjour, êtes-vous ouverts le samedi ?')
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors();

        $stored = QuoteRequest::query()->sole();
        $this->assertSame(QuoteRequestMode::Contact, $stored->mode);
        $this->assertSame(QuoteRequestStatus::New, $stored->status);
        $this->assertSame('Jean Dupont', $stored->name);
        $this->assertSame('jean@example.test', $stored->email);
        $this->assertNull($stored->phone);
        $this->assertSame('Horaires', $stored->subject);
        $this->assertSame('Bonjour, êtes-vous ouverts le samedi ?', $stored->message);
    }

    public function test_mail_failure_keeps_the_message_and_shows_success(): void
    {
        config(['storefront.contact.email' => 'boutique@example.test']);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        Livewire::test(ContactPage::class)
            ->set('name', 'Jean Dupont')
            ->set('email', 'jean@example.test')
            ->set('subject', 'Horaires')
            ->set('message', 'Bonjour, êtes-vous ouverts le samedi ?')
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $this->assertSame(1, QuoteRequest::query()->count());
    }

    public function test_validation_errors_block_submission(): void
    {
        Mail::fake();

        Livewire::test(ContactPage::class)
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'subject', 'message', 'consent']);

        Mail::assertNothingSent();
        $this->assertSame(0, QuoteRequest::query()->count());
    }

    public function test_consent_is_required(): void
    {
        Mail::fake();

        Livewire::test(ContactPage::class)
            ->set('name', 'Alice')
            ->set('email', 'alice@example.test')
            ->set('subject', 'Autre')
            ->set('message', 'Un message suffisamment long pour être valide.')
            ->set('consent', false)
            ->call('submit')
            ->assertHasErrors(['consent'])
            ->assertSet('sent', false);

        Mail::assertNothingSent();
    }

    public function test_honeypot_silently_drops_bot_submission(): void
    {
        Mail::fake();

        Livewire::test(ContactPage::class)
            ->set('website', 'http://spam.test')
            ->set('name', 'Bot')
            ->set('email', 'bot@spam.test')
            ->set('subject', 'Autre')
            ->set('message', 'spam spam spam spam spam')
            ->set('consent', true)
            ->call('submit')
            ->assertSet('sent', true);

        Mail::assertNothingSent();
        $this->assertSame(0, QuoteRequest::query()->count());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\QuoteRequests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Mail\QuoteRequestAdminMail;
use Pko\QuoteRequests\Mail\QuoteRequestReceivedMail;
use Pko\QuoteRequests\Models\QuoteRequest;
use Pko\QuoteRequests\Support\MailAttachmentSelection;
use Tests\TestCase;

class QuoteRequestMailAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['quote-requests.attachments.disk' => 'local']);
        // Plafond de 10 Ko pour tester le partage sans fabriquer des Mo.
        config(['quote-requests.attachments.mail_max_total_kb' => 10]);
    }

    private function request(): QuoteRequest
    {
        return QuoteRequest::create([
            'mode' => QuoteRequestMode::PartPhoto,
            'name' => 'Camille Martin',
            'email' => 'camille@example.test',
            'message' => 'Plaque illisible.',
        ]);
    }

    private function addFile(QuoteRequest $request, string $name, int $sizeKb, bool $onDisk = true, string $mime = 'image/png'): void
    {
        $path = 'quote-requests/'.$request->id.'/'.$name;

        if ($onDisk) {
            Storage::disk('local')->put($path, str_repeat('x', $sizeKb * 1024));
        }

        $request->files()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => $name,
            'mime_type' => $mime,
            'size' => $sizeKb * 1024,
        ]);
    }

    public function test_files_under_the_cap_are_attached_to_the_team_mail(): void
    {
        $request = $this->request();
        $this->addFile($request, 'etiquette.png', 3);
        $this->addFile($request, 'plan.pdf', 4, mime: 'application/pdf');

        $mail = new QuoteRequestAdminMail($request->fresh());
        $attachments = $mail->attachments();

        $this->assertCount(2, $attachments);
        $this->assertSame('etiquette.png', $attachments[0]->as);
        $this->assertSame('application/pdf', $attachments[1]->mime);
        $this->assertStringContainsString('etiquette.png (joint à ce mail)', $mail->render());

        // Envoi réel via le transport « array » : les fichiers sont bien dans le message.
        config(['mail.default' => 'array']);
        Mail::mailer('array')->to('equipe@example.test')->sendNow(new QuoteRequestAdminMail($request->fresh()));
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $names = array_map(fn ($part) => $part->getFilename(), $sent->getAttachments());
        $this->assertSame(['etiquette.png', 'plan.pdf'], $names);
    }

    public function test_a_file_over_the_remaining_budget_is_skipped_but_a_smaller_one_still_fits(): void
    {
        $request = $this->request();
        $this->addFile($request, 'vue.png', 6);
        $this->addFile($request, 'gros-plan.png', 8);
        $this->addFile($request, 'detail.png', 3);

        $selection = MailAttachmentSelection::for($request->fresh());

        $this->assertSame(['vue.png', 'detail.png'], $selection->attached->pluck('original_name')->all());
        $this->assertSame(['gros-plan.png'], $selection->notAttached->pluck('original_name')->all());
        $this->assertStringContainsString('gros-plan.png (8 Ko, à télécharger dans l\'admin)', $selection->summary());
    }

    public function test_a_file_missing_on_disk_is_never_attached(): void
    {
        $request = $this->request();
        $this->addFile($request, 'perdu.png', 1, onDisk: false);

        $mail = new QuoteRequestAdminMail($request->fresh());

        $this->assertSame([], $mail->attachments());
        $this->assertStringContainsString('perdu.png', $mail->render());
    }

    public function test_no_file_gives_the_none_label_and_no_attachment(): void
    {
        $request = $this->request();

        $this->assertSame('aucune', MailAttachmentSelection::for($request)->summary());
        $this->assertSame([], (new QuoteRequestAdminMail($request))->attachments());
    }

    public function test_the_customer_acknowledgement_never_carries_attachments(): void
    {
        $request = $this->request();
        $this->addFile($request, 'etiquette.png', 1);

        $mail = new QuoteRequestReceivedMail($request->fresh());
        $mail->render();

        $this->assertSame([], $mail->attachments);
    }
}

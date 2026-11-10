<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Attachment;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\QuoteRequests\Models\QuoteRequest;
use Pko\QuoteRequests\Models\QuoteRequestFile;
use Pko\QuoteRequests\Support\MailAttachmentSelection;

/**
 * Notification équipe : coordonnées, contexte d'origine et lien vers la fiche
 * admin. Les pièces jointes sont attachées tant que leur cumul reste sous le
 * plafond `attachments.mail_max_total_kb` ; les autres se téléchargent depuis
 * l'admin (cf. MailAttachmentSelection).
 */
class QuoteRequestAdminMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly QuoteRequest $quoteRequest)
    {
        parent::__construct('quote_request.received_admin', QuoteRequestMailData::admin($quoteRequest));

        // Répondre au demandeur d'un clic depuis la boîte de l'équipe.
        $this->replyTo($quoteRequest->email, $quoteRequest->name);
    }

    /**
     * Résolu à l'envoi (dans le worker) : lit les fichiers sur le disque privé.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return MailAttachmentSelection::for($this->quoteRequest)->attached
            ->map(fn (QuoteRequestFile $file): Attachment => Attachment::fromStorageDisk($file->disk, $file->path)
                ->as($file->original_name)
                ->withMime($file->mime_type))
            ->all();
    }
}

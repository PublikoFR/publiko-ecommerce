<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\QuoteRequests\Models\QuoteRequest;

/**
 * Notification équipe : coordonnées, contexte d'origine et lien vers la fiche
 * admin (où se téléchargent les pièces jointes — jamais attachées au mail :
 * elles peuvent peser 80 Mo et passent par un disque privé).
 */
class QuoteRequestAdminMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly QuoteRequest $quoteRequest)
    {
        parent::__construct('quote_request.received_admin', QuoteRequestMailData::admin($quoteRequest));

        // Répondre au demandeur d'un clic depuis la boîte de l'équipe.
        $this->replyTo($quoteRequest->email, $quoteRequest->name);
    }
}

<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\QuoteRequests\Models\QuoteRequest;

/**
 * Accusé de réception envoyé au demandeur.
 */
class QuoteRequestReceivedMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly QuoteRequest $quoteRequest)
    {
        parent::__construct('quote_request.received', QuoteRequestMailData::customer($quoteRequest));
    }
}

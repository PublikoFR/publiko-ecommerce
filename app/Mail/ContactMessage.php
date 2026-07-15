<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $senderPhone,
        public readonly string $subjectLine,
        public readonly string $body,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Contact boutique — '.$this->subjectLine)
            ->replyTo($this->senderEmail, $this->senderName)
            ->view('mail.contact-message');
    }
}

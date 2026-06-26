<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;

class QuotePaymentLinkMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $paymentUrl,
        public readonly int $transportCents,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Votre devis — lien de paiement')
            ->view('pko-shipping-common::mail.quote-payment-link');
    }
}

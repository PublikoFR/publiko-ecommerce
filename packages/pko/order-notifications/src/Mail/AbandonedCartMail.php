<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\Models\Cart;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * E-mail 11 « Panier non finalisé ».
 */
class AbandonedCartMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly Cart $cart)
    {
        parent::__construct('cart.abandoned', [
            'first_name' => (string) ($cart->customer?->first_name ?? $cart->user?->name ?? ''),
            'cart_url' => url('/panier'),
        ]);
    }
}

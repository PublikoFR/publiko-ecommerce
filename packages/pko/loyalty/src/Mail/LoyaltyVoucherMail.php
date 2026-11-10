<?php

declare(strict_types=1);

namespace Pko\Loyalty\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Route;
use Lunar\Models\Customer;
use Lunar\Models\Discount;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\Loyalty\Services\LoyaltyVoucherService;
use Pko\Loyalty\Support\Money;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * Bon d'achat fidélité émis au déblocage d'un palier « bon d'achat » : code,
 * montant HT et date d'expiration. Remplace, pour ce type de palier, l'e-mail
 * générique « Nouveau palier » (un seul message client par déblocage).
 */
class LoyaltyVoucherMail extends TemplatedMail implements ShouldQueue
{
    public function __construct(
        public readonly ?Customer $customer,
        public readonly LoyaltyTier $tier,
        public readonly Discount $discount,
    ) {
        parent::__construct('loyalty.voucher_issued', [
            'first_name' => (string) ($customer?->first_name ?? ''),
            'tier_name' => (string) $tier->name,
            'voucher_code' => (string) $discount->coupon,
            'voucher_amount' => Money::format(LoyaltyVoucherService::amount($discount)),
            'voucher_expires_at' => (string) $discount->ends_at?->format('d/m/Y'),
            'account_url' => Route::has('account.loyalty') ? route('account.loyalty') : url('/compte'),
        ]);
    }
}

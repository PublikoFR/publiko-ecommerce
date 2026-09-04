<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use Illuminate\View\View;
use Livewire\Component;
use Lunar\Facades\CartSession;
use Lunar\Facades\Discounts;
use Lunar\Models\Cart;

class CouponCode extends Component
{
    public string $code = '';

    protected $listeners = [
        'cartUpdated' => '$refresh',
    ];

    public function apply(): void
    {
        $this->resetErrorBag();

        $this->validate(
            ['code' => ['required', 'string', 'max:40']],
            [
                'code.required' => 'Saisissez un code promo.',
                'code.max' => 'Ce code promo est trop long.',
            ],
        );

        $code = strtoupper(trim($this->code));
        $cart = $this->currentCart(calculate: false);

        if (! $cart || $cart->lines->isEmpty()) {
            $this->addError('code', 'Votre panier est vide.');

            return;
        }

        if (! Discounts::validateCoupon($code)) {
            $this->addError('code', 'Ce code promo n\'est pas valide ou a déjà été utilisé.');

            return;
        }

        $cart->coupon_code = $code;
        $cart->save();

        $this->recalculate($cart);

        if (($cart->discountTotal?->value ?? 0) <= 0) {
            $cart->coupon_code = null;
            $cart->save();
            $this->recalculate($cart);
            $this->addError('code', 'Ce code promo ne s\'applique pas aux articles de votre panier.');

            return;
        }

        $this->code = '';
        $this->dispatch('cartUpdated');
    }

    public function remove(): void
    {
        $cart = $this->currentCart(calculate: false);

        if (! $cart) {
            return;
        }

        $cart->coupon_code = null;
        $cart->save();
        $this->recalculate($cart);

        $this->code = '';
        $this->resetErrorBag();
        $this->dispatch('cartUpdated');
    }

    public function getAppliedCodeProperty(): ?string
    {
        $code = $this->currentCart()?->coupon_code;

        return filled($code) ? (string) $code : null;
    }

    public function render(): View
    {
        return view('livewire.components.coupon-code');
    }

    private function currentCart(bool $calculate = true): ?Cart
    {
        try {
            return CartSession::current(false, $calculate);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Le DiscountManager cache les remises applicables pour la requête :
     * sans reset, un coupon saisi après le premier calcul est ignoré.
     */
    private function recalculate(Cart $cart): void
    {
        Discounts::resetDiscounts();
        $cart->recalculate();
    }
}

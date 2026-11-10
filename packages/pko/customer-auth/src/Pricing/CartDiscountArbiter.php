<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Pricing;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Lunar\DataTypes\Price;
use Lunar\Facades\Discounts;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Discount;

/**
 * Non-cumul remise client % ↔ promotions/coupons Lunar (au niveau du panier).
 *
 * Le panier arrive avec des lignes au prix client (remise % déjà intégrée par
 * NegotiatedPricePipeline). Quand le client a des remises, deux calculs :
 *
 *  A. prix client (remise %) + bons cumulables seulement ;
 *  B. prix SANS remise % (prix négocié conservé) + promotions/coupons Lunar.
 *
 * Le total HT de A (avant bons) est comparé au total HT de B après promotions :
 * le plus bas gagne, égalité → remise client. Les bons cumulables (bon d'achat
 * fidélité, types listés dans `customer-auth.stackable_discount_types`) sont
 * ensuite appliqués sur l'état retenu, toujours.
 *
 * Lié en `scoped` : la décision du dernier calcul de chaque panier est gardée
 * pour l'affichage (decisionFor()).
 */
class CartDiscountArbiter
{
    private const LINE_PROPERTIES = [
        'unitPrice', 'unitPriceInclTax', 'subTotal', 'subTotalDiscounted', 'discountTotal', 'taxAmount', 'total',
    ];

    /** @var array<int|string, CartAdvantage|null> */
    private array $decisions = [];

    public function __construct(private readonly CustomerDiscountResolver $resolver) {}

    public function apply(Cart $cart): Cart
    {
        $cart->discounts = collect();
        $cart->discountBreakdown = collect();

        $all = Discounts::getDiscounts($cart);
        [$stackable, $exclusive] = $all->partition(fn (Discount $discount): bool => $this->isStackable($discount));

        $customerIds = $this->resolver->customerIdsFor($this->pricingUser());

        if (! $this->resolver->hasDiscounts($customerIds)) {
            $this->run($cart, $all);
            $this->decisions[$this->key($cart)] = null;

            return $cart;
        }

        $customerState = $this->snapshot($cart);
        $customerTotal = $this->sum($cart, 'subTotal');

        $this->resolver->withoutPercentDiscount(fn () => $this->repriceLines($cart));
        $customerSaving = $this->sum($cart, 'subTotal') - $customerTotal;

        if ($customerSaving <= 0) {
            // Aucune ligne remisée : rien à arbitrer, comportement Lunar standard.
            $this->restore($cart, $customerState);
            $this->run($cart, $all);
            $this->decisions[$this->key($cart)] = null;

            return $cart;
        }

        $promotionSaving = 0;
        $couponApplied = false;

        if ($exclusive->isNotEmpty()) {
            $this->run($cart, $exclusive);
            $promotionSaving = $this->sum($cart, 'subTotal') - $this->sum($cart, 'subTotalDiscounted');
            $couponApplied = filled($cart->coupon_code) && $cart->discounts->contains(
                fn ($type) => strtoupper((string) $type->discount?->coupon) === strtoupper((string) $cart->coupon_code)
            );
        }

        $promotionTotal = $customerTotal + $customerSaving - $promotionSaving;
        $promotionWins = $promotionSaving > 0 && $promotionTotal < $customerTotal;

        if (! $promotionWins) {
            $this->restore($cart, $customerState);
        }

        $before = $this->sum($cart, 'discountTotal');
        $this->run($cart, $stackable);
        $stacked = $this->sum($cart, 'discountTotal') - $before;

        $this->decisions[$this->key($cart)] = new CartAdvantage(
            winner: $promotionWins ? CartAdvantage::WINNER_PROMOTION : CartAdvantage::WINNER_CUSTOMER_DISCOUNT,
            customerDiscountCents: $customerSaving,
            promotionCents: max(0, $promotionSaving),
            currency: $cart->currency,
            outrankedCoupon: ! $promotionWins && $couponApplied ? strtoupper((string) $cart->coupon_code) : null,
            stackedCents: $stacked,
        );

        return $cart;
    }

    /**
     * Avantage retenu sur le panier (null : aucune remise client en jeu). Calcule
     * le panier si cette requête ne l'a pas encore fait.
     */
    public function decisionFor(?Cart $cart): ?CartAdvantage
    {
        if ($cart === null || $cart->lines->isEmpty()) {
            return null;
        }

        if (! array_key_exists($this->key($cart), $this->decisions)) {
            $cart->recalculate();
        }

        return $this->decisions[$this->key($cart)] ?? null;
    }

    /** @return array{winner: string, headline: string, details: list<string>}|null */
    public function displayFor(?Cart $cart): ?array
    {
        return $this->decisionFor($cart)?->toDisplay();
    }

    public function isStackable(Discount $discount): bool
    {
        return in_array($discount->type, (array) config('customer-auth.stackable_discount_types', []), true);
    }

    /** @param  Collection<int, Discount>  $discounts */
    private function run(Cart $cart, Collection $discounts): void
    {
        foreach ($discounts as $discount) {
            $cart = $discount->getType()->apply($cart);
        }
    }

    /** Recalcule prix unitaires et sous-totaux comme CalculateLines. */
    private function repriceLines(Cart $cart): void
    {
        foreach ($cart->lines as $line) {
            $line = app(Pipeline::class)
                ->send($line)
                ->through(config('lunar.cart.pipelines.cart_lines', []))
                ->thenReturn();

            $unitPrice = $line->unitPrice->unitDecimal(false) * $cart->currency->factor;
            $subTotal = (int) round($unitPrice * $line->quantity, $cart->currency->decimal_places);

            $line->subTotal = new Price($subTotal, $cart->currency, 1);
            $line->taxAmount = new Price(0, $cart->currency, 1);
            $line->total = new Price($subTotal, $cart->currency, 1);
            $line->subTotalDiscounted = new Price($subTotal, $cart->currency, 1);
            $line->discountTotal = new Price(0, $cart->currency, 1);
        }
    }

    private function sum(Cart $cart, string $property): int
    {
        return (int) $cart->lines->sum(fn (CartLine $line) => $line->{$property}?->value ?? 0);
    }

    /** @return array{lines: array<int, array<string, mixed>>, cart: array<string, mixed>} */
    private function snapshot(Cart $cart): array
    {
        $lines = [];
        foreach ($cart->lines as $index => $line) {
            foreach (self::LINE_PROPERTIES as $property) {
                $lines[$index][$property] = $line->{$property};
            }
            // BuyXGetY écrit dans `meta` des lignes.
            $lines[$index]['meta'] = $line->getAttributes()['meta'] ?? null;
        }

        return [
            'lines' => $lines,
            'cart' => [
                // Copies : les types de remise font `push` sur ces collections.
                'discounts' => $cart->discounts?->all() ?? [],
                'discountBreakdown' => $cart->discountBreakdown?->all() ?? [],
                'freeItems' => $cart->freeItems?->all(),
                'promotions' => $cart->promotions?->all(),
            ],
        ];
    }

    /** @param  array{lines: array<int, array<string, mixed>>, cart: array<string, mixed>}  $state */
    private function restore(Cart $cart, array $state): void
    {
        foreach ($cart->lines as $index => $line) {
            $saved = $state['lines'][$index] ?? null;
            if ($saved === null) {
                continue;
            }

            foreach (self::LINE_PROPERTIES as $property) {
                $line->{$property} = $saved[$property];
            }

            $attributes = $line->getAttributes();
            $attributes['meta'] = $saved['meta'];
            $line->setRawAttributes($attributes);
        }

        $cart->discounts = collect($state['cart']['discounts'] ?? []);
        $cart->discountBreakdown = collect($state['cart']['discountBreakdown'] ?? []);
        $cart->freeItems = $state['cart']['freeItems'] !== null ? collect($state['cart']['freeItems']) : null;
        $cart->promotions = $state['cart']['promotions'] !== null ? collect($state['cart']['promotions']) : null;
    }

    /** Même utilisateur que le PricingManager Lunar (cf. NegotiatedPricePipeline). */
    private function pricingUser(): mixed
    {
        return Auth::check() && is_lunar_user(Auth::user()) ? Auth::user() : null;
    }

    private function key(Cart $cart): int|string
    {
        return $cart->getKey() ?? spl_object_id($cart);
    }
}

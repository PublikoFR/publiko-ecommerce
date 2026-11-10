<?php

declare(strict_types=1);

namespace Pko\Storefront\Support;

use Lunar\DataTypes\Price;
use Lunar\Facades\Pricing;
use Lunar\Models\CartLine;
use Lunar\Models\Contracts\Currency as CurrencyContract;
use Lunar\Models\Currency;
use Lunar\Models\ProductVariant;
use Pko\CustomerAuth\Pricing\CustomerDiscountResolver;
use Spatie\LaravelBlink\BlinkFacade as Blink;

/**
 * Prix HT affiché au client connecté, avec le détail « prix barré + badge -X % »
 * quand une remise client % fixe le prix final.
 *
 * Source unique : CustomerDiscountResolver::priceFor() (pko/lunar-customer-auth),
 * qui passe par le PricingManager Lunar → même prix que le panier, la commande,
 * le devis et la facture. Un prix négocié seul (ou catalogue) n'a ni barré ni
 * badge. Dépendance souple : sans le package customer-auth, prix Lunar brut.
 */
final class CustomerPriceDisplay
{
    public function __construct(
        public readonly Price $price,
        public readonly ?Price $original = null,
        public readonly ?float $percent = null,
    ) {}

    /** Prix d'une variante pour l'utilisateur connecté (null si aucun prix). */
    public static function forVariant(ProductVariant $variant, ?CurrencyContract $currency = null, int $qty = 1): ?self
    {
        $currency ??= self::defaultCurrency();

        if ($currency === null) {
            return null;
        }

        $resolver = self::resolver();

        if ($resolver === null) {
            $matched = Pricing::for($variant)->currency($currency)->qty($qty)->get()->matched;

            return new self($matched->price);
        }

        $breakdown = $resolver->priceFor($variant, null, $currency, $qty);
        $unitQty = (int) ($variant->getUnitQuantity() ?: 1);
        $price = new Price($breakdown->finalCents, $currency, $unitQty);

        if (! $breakdown->isDiscounted()) {
            return new self($price);
        }

        return new self(
            $price,
            new Price($breakdown->originalCents, $currency, $unitQty),
            $breakdown->appliedDiscountPercent(),
        );
    }

    /**
     * Prix unitaire d'une ligne de panier déjà calculée. Le barré n'est affiché
     * que si le détail recalculé correspond exactement au prix unitaire facturé.
     */
    public static function forCartLine(CartLine $line): self
    {
        $unitPrice = $line->unitPrice;
        $purchasable = $line->purchasable;
        $resolver = self::resolver();

        if ($resolver === null || ! $purchasable instanceof ProductVariant || $unitPrice === null) {
            return new self($unitPrice);
        }

        try {
            $breakdown = $resolver->priceFor(
                $purchasable,
                $line->cart?->user,
                $line->cart?->currency,
                (int) $line->quantity,
            );
        } catch (\Throwable) {
            return new self($unitPrice);
        }

        if (! $breakdown->isDiscounted() || $breakdown->finalCents !== (int) $unitPrice->value) {
            return new self($unitPrice);
        }

        return new self(
            $unitPrice,
            new Price($breakdown->originalCents, $unitPrice->currency, $unitPrice->unitQty),
            $breakdown->appliedDiscountPercent(),
        );
    }

    /** Précharge les cibles de remise d'un lot de produits (listings, anti N+1). */
    public static function preloadProducts(iterable $products): void
    {
        self::resolver()?->preloadProducts($products);
    }

    public function isDiscounted(): bool
    {
        return $this->original !== null && $this->percent !== null;
    }

    /** « -12,5 % » (espace insécable), null sans remise appliquée. */
    public function badgeLabel(): ?string
    {
        if (! $this->isDiscounted()) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format($this->percent, 2, ',', ''), '0'), ',');

        return '-'.$formatted."\u{00A0}%";
    }

    /** Résolveur uniquement pour un client connecté (les invités ne voient pas de prix). */
    private static function resolver(): ?CustomerDiscountResolver
    {
        if (! auth()->check() || ! class_exists(CustomerDiscountResolver::class)) {
            return null;
        }

        return app(CustomerDiscountResolver::class);
    }

    private static function defaultCurrency(): ?CurrencyContract
    {
        return Blink::once('pko_storefront_default_currency', fn () => Currency::getDefault());
    }
}

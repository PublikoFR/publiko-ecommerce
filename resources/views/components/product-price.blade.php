<span {{ $attributes }}>
    {{ $price?->price->formatted() }}
    <x-storefront.ecotax-note :cents="\App\Support\Ecotax::forPurchasable($price?->priceable)" :currency="$price?->price->currency" />
</span>

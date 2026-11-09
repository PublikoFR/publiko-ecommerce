@props(['product' => null, 'variant' => null, 'size' => 'md'])

@php
use Lunar\Facades\Pricing;

// Prix réservés aux clients connectés (tout compte authentifié).
$isPro = auth()->check();

$sizeClasses = [
    'sm' => 'font-display font-bold text-lg',
    'md' => 'font-display font-bold text-2xl',
    'lg' => 'font-display font-bold text-3xl',
    'xl' => 'font-display font-bold text-4xl',
];
$priceClass = $sizeClasses[$size] ?? $sizeClasses['md'];
$ctaSize = $size === 'lg' || $size === 'xl' ? 'md' : 'sm';

if ($isPro) {
    try {
        $target = $variant ?: $product?->variants?->first();
        $priced = $target ? Pricing::for($target)->get()->matched : null;
    } catch (\Throwable) {
        $priced = null;
    }
}
@endphp

@if ($isPro)
    @if (! empty($priced))
        <div {{ $attributes->class(['text-primary-600 leading-none', $priceClass]) }}>
            {{ $priced->price->formatted() }}
            <span class="text-[10px] font-sans font-semibold text-neutral-500 uppercase tracking-wide ml-1">HT</span>
            {{-- Éco-participation incluse dans le prix (modèle « dont ») : suit la variante ciblée. --}}
            <x-storefront.ecotax-note :cents="\App\Support\Ecotax::forPurchasable($target)" :currency="$priced->price->currency" class="mt-1.5" />
        </div>
    @else
        <span class="text-sm text-neutral-500 italic">Prix sur demande</span>
    @endif
@else
    <p class="text-sm text-neutral-500 italic">Prix réservé aux professionnels connectés</p>
@endif

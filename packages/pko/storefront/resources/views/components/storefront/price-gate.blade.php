@props(['product' => null, 'variant' => null, 'size' => 'md'])

@php
use Pko\Storefront\Support\CustomerPriceDisplay;

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
$strikeClasses = [
    'sm' => 'text-xs',
    'md' => 'text-sm',
    'lg' => 'text-base',
    'xl' => 'text-lg',
];
$strikeClass = $strikeClasses[$size] ?? $strikeClasses['md'];
$badgeSize = $size === 'lg' || $size === 'xl' ? 'md' : 'sm';

if ($isPro) {
    try {
        $target = $variant ?: $product?->variants?->first();
        // Prix client unifié (prix négocié / remise client %) : même source que
        // le panier, la commande, le devis et la facture.
        $display = $target ? CustomerPriceDisplay::forVariant($target) : null;
        $priced = $display?->price;
    } catch (\Throwable) {
        $priced = null;
    }
}
@endphp

@if ($isPro)
    @if (! empty($priced))
        <div {{ $attributes->class(['text-primary-600 leading-none', $priceClass]) }}>
            {{-- Remise client % : prix barré + badge. Un prix négocié seul reste un prix net. --}}
            @if ($display->isDiscounted())
                <div class="flex flex-wrap items-center gap-1.5 mb-1 font-sans font-normal" data-customer-discount>
                    <s class="{{ $strikeClass }} text-neutral-500" aria-label="Prix avant remise">{{ $display->original->formatted() }}</s>
                    <x-ui.badge tone="forest" variant="solid" :size="$badgeSize">{{ $display->badgeLabel() }}</x-ui.badge>
                </div>
            @endif
            {{ $priced->formatted() }}
            <span class="text-[10px] font-sans font-semibold text-neutral-500 uppercase tracking-wide ml-1">HT</span>
            {{-- Éco-participation incluse dans le prix (modèle « dont ») : suit la variante ciblée. --}}
            <x-storefront.ecotax-note :cents="\App\Support\Ecotax::forPurchasable($target)" :currency="$priced->currency" class="mt-1.5" />
        </div>
    @else
        <span class="text-sm text-neutral-500 italic">Prix sur demande</span>
    @endif
@else
    <p class="text-sm text-neutral-500 italic">Prix réservé aux professionnels connectés</p>
@endif

<button type="button" x-data @click="$dispatch('open-cart-drawer')" class="relative flex flex-col items-center gap-0.5 px-2.5 py-1.5 rounded-md text-primary-600 hover:bg-primary-50 transition">
    <div class="relative">
        <x-ui.icon name="cart" class="w-[22px] h-[22px]" />
        @if ($count > 0)
            <span class="absolute -top-2 -right-2.5 bg-accent-500 text-primary-700 text-[11px] font-bold font-mono rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $count }}</span>
        @endif
    </div>
    <span class="text-[11px] font-medium text-neutral-600">Panier</span>
</button>

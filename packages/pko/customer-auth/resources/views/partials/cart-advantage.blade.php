{{--
    Avantage retenu sur le panier (non-cumul remise client % / promotions).
    $advantage : CartDiscountArbiter::displayFor() (array|null)
--}}
@if ($advantage)
    <div class="rounded-md border border-primary-100 bg-primary-50 px-3 py-2.5 text-sm" data-cart-advantage="{{ $advantage['winner'] }}">
        <div class="flex items-start gap-2">
            <x-ui.icon name="tag" class="w-4 h-4 mt-0.5 text-primary-700 shrink-0" />
            <div class="min-w-0 space-y-0.5">
                <p class="font-semibold text-primary-800">{{ $advantage['headline'] }}</p>
                @foreach ($advantage['details'] as $detail)
                    <p class="text-xs text-neutral-700">{{ $detail }}</p>
                @endforeach
            </div>
        </div>
    </div>
@endif

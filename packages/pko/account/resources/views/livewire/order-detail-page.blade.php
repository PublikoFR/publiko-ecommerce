<div class="space-y-6">
    <div>
        <a href="{{ route('account.orders') }}" class="text-sm text-primary-600 hover:text-primary-700 font-semibold">← Mes commandes</a>
        <h1 class="text-2xl font-black text-neutral-900 mt-1">Commande #{{ $order->reference ?? $order->id }}</h1>
        <p class="text-sm text-neutral-500 mt-1">{{ optional($order->placed_at)->format('d/m/Y H:i') }} · <x-ui.badge variant="primary">{{ $order->status }}</x-ui.badge></p>
    </div>

    <x-ui.card padding="lg">
        <h2 class="font-bold text-neutral-900 mb-4">Articles commandés</h2>
        <ul class="divide-y divide-neutral-100">
            @foreach ($order->lines as $line)
                <li class="py-3 flex items-center justify-between text-sm">
                    <div>
                        <p class="font-semibold text-neutral-900">{{ $line->description }}</p>
                        <p class="text-xs text-neutral-500 mt-0.5">{{ $line->identifier }} · Qté {{ $line->quantity }}</p>
                    </div>
                    <p class="font-semibold text-neutral-900">{{ $line->sub_total?->formatted() }}</p>
                </li>
            @endforeach
        </ul>

        <div class="mt-6 pt-4 border-t border-neutral-200 space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-neutral-500">Sous-total</span><span>{{ $order->sub_total?->formatted() }}</span></div>
            <div class="flex justify-between"><span class="text-neutral-500">Livraison</span><span>{{ $order->shipping_total?->formatted() }}</span></div>
            <div class="flex justify-between"><span class="text-neutral-500">TVA</span><span>{{ $order->tax_total?->formatted() }}</span></div>
            <div class="flex justify-between pt-2 border-t border-neutral-200 text-lg font-black"><span>Total TTC</span><span>{{ $order->total?->formatted() }}</span></div>
        </div>
    </x-ui.card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @foreach ([['Adresse de facturation', $order->billingAddress], ['Adresse de livraison', $order->shippingAddress]] as [$title, $addr])
            <x-ui.card padding="lg">
                <h3 class="font-bold text-neutral-900 mb-2">{{ $title }}</h3>
                @if ($addr)
                    <address class="not-italic text-sm text-neutral-700 leading-relaxed">
                        <p class="font-semibold">{{ $addr->first_name }} {{ $addr->last_name }}</p>
                        @if ($addr->company_name)<p>{{ $addr->company_name }}</p>@endif
                        <p>{{ $addr->line_one }}</p>
                        @if ($addr->line_two)<p>{{ $addr->line_two }}</p>@endif
                        <p>{{ $addr->postcode }} {{ $addr->city }}</p>
                    </address>
                @else
                    <p class="text-sm text-neutral-500">—</p>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</div>

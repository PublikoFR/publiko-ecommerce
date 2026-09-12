<div class="space-y-6">
    @if (session('checkout_confirmed'))
        <x-ui.alert variant="success" title="Commande validée">
            Votre commande a bien été enregistrée et votre paiement confirmé. Merci pour votre confiance !
        </x-ui.alert>
    @endif

    <div>
        <a href="{{ route('account.orders') }}" class="text-sm text-primary-600 hover:text-primary-700 font-semibold">← Mes commandes</a>
        <h1 class="text-2xl font-display font-bold text-neutral-900 mt-1">Commande #{{ $order->reference ?? $order->id }}</h1>
        <p class="text-sm text-neutral-500 mt-1">{{ optional($order->placed_at)->format('d/m/Y H:i') }} · <x-ui.badge variant="primary">{{ $order->status }}</x-ui.badge></p>
    </div>

    {{-- Nom du chantier --}}
    <x-ui.card padding="lg">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h2 class="font-bold text-neutral-900 mb-1">Nom du chantier</h2>
                @if (! $editingSiteName)
                    <p class="text-sm text-neutral-700">
                        {{ $order->pko_site_name ?: '—' }}
                    </p>
                @else
                    <div class="mt-2 space-y-3">
                        <x-ui.input
                            wire:model="siteName"
                            placeholder="Ex. : Résidence Les Acacias — Lot A"
                            maxlength="255"
                            :error="$errors->first('siteName')"
                        />
                        <div class="flex gap-2">
                            <x-ui.button wire:click="saveSiteName" size="sm" variant="primary">Enregistrer</x-ui.button>
                            <x-ui.button wire:click="cancelEditSiteName" size="sm" variant="secondary">Annuler</x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
            @if (! $editingSiteName)
                <button wire:click="$set('editingSiteName', true)"
                        class="shrink-0 text-sm font-medium text-primary-600 hover:text-primary-700">
                    Modifier
                </button>
            @endif
        </div>
    </x-ui.card>

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
            <div class="flex justify-between pt-2 border-t border-neutral-200 text-lg font-display font-bold"><span>Total TTC</span><span>{{ $order->total?->formatted() }}</span></div>
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

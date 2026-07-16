<section class="py-16 md:py-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- En-tête confirmation --}}
        <div class="text-center mb-10">
            <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-success-100 text-success-700 mb-6">
                <x-ui.icon name="check" class="w-8 h-8" />
            </span>
            <h1 class="font-display font-bold text-3xl md:text-4xl text-neutral-900">
                Commande confirmée
            </h1>
            <p class="mt-3 text-neutral-600">
                Merci pour votre commande. Votre numéro de référence est
                <strong class="font-mono text-primary-700">{{ $order->reference }}</strong>.
            </p>
        </div>

        @if ($order->status === 'awaiting-quote')
            <x-ui.card padding="md" class="mb-6 !bg-warning-50 !border-warning-100">
                <div class="flex gap-3 text-sm text-warning-700">
                    <x-ui.icon name="truck" class="w-5 h-5 shrink-0 text-warning-500 mt-0.5" />
                    <p>Votre commande nécessite un devis transport. Vous recevrez un lien de paiement avec le montant final une fois les frais de port calculés.</p>
                </div>
            </x-ui.card>
        @endif

        @if ($order->status === 'payment-pending')
            <x-ui.card padding="md" class="mb-6 !bg-info-50 !border-info-100">
                <div class="flex gap-3 text-sm text-info-700">
                    <x-ui.icon name="clock" class="w-5 h-5 shrink-0 text-info-500 mt-0.5" />
                    <p>Votre prélèvement SEPA est en cours de traitement. La confirmation bancaire peut prendre 2–5 jours ouvrés.</p>
                </div>
            </x-ui.card>
        @endif

        {{-- Récapitulatif commande --}}
        <x-ui.card padding="none" class="mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-100 bg-neutral-50">
                <h2 class="font-semibold text-neutral-800">Articles commandés</h2>
            </div>
            <div class="divide-y divide-neutral-100">
                @foreach ($order->lines as $line)
                    <div class="flex items-center gap-4 px-6 py-4">
                        @php($thumbnail = $line->purchasable?->getThumbnail())
                        @if ($thumbnail)
                            <img src="{{ $thumbnail->getUrl() }}" alt="{{ $line->purchasable->getDescription() }}"
                                 class="w-14 h-14 object-cover rounded-md shrink-0" />
                        @else
                            <div class="w-14 h-14 rounded-md bg-neutral-100 shrink-0 flex items-center justify-center text-neutral-300">
                                <x-ui.icon name="package" class="w-6 h-6" />
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-neutral-900 truncate">{{ $line->description }}</p>
                            @if ($line->identifier)
                                <p class="text-xs font-mono text-neutral-400 mt-0.5">{{ $line->identifier }}</p>
                            @endif
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm text-neutral-500">{{ $line->quantity }} ×</p>
                            <p class="text-sm font-medium text-neutral-900">{{ $line->subTotal->formatted() }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Totaux --}}
            <div class="px-6 py-4 border-t border-neutral-100 space-y-2">
                <div class="flex justify-between text-sm text-neutral-600">
                    <span>Sous-total HT</span>
                    <span>{{ $order->sub_total->formatted() }}</span>
                </div>
                @foreach ($order->taxBreakdown->amounts as $tax)
                    <div class="flex justify-between text-sm text-neutral-600">
                        <span>{{ $tax->description }}</span>
                        <span>{{ $tax->price->formatted() }}</span>
                    </div>
                @endforeach
                @if ($order->shipping_total?->value > 0)
                    <div class="flex justify-between text-sm text-neutral-600">
                        <span>Livraison</span>
                        <span>{{ $order->shipping_total->formatted() }}</span>
                    </div>
                @endif
                <div class="flex justify-between font-semibold text-neutral-900 pt-2 border-t border-neutral-100">
                    <span>Total TTC</span>
                    <span class="font-display text-lg text-primary-700">{{ $order->total->formatted() }}</span>
                </div>
            </div>
        </x-ui.card>

        {{-- Adresses + paiement --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
            @if ($order->shippingAddress)
                <x-ui.card padding="md">
                    <h3 class="font-semibold text-neutral-800 mb-3">Adresse de livraison</h3>
                    <address class="not-italic text-sm text-neutral-600 space-y-0.5">
                        <p class="font-medium text-neutral-900">{{ $order->shippingAddress->company_name ?: ($order->shippingAddress->first_name . ' ' . $order->shippingAddress->last_name) }}</p>
                        <p>{{ $order->shippingAddress->line_one }}</p>
                        @if ($order->shippingAddress->line_two)
                            <p>{{ $order->shippingAddress->line_two }}</p>
                        @endif
                        <p>{{ $order->shippingAddress->postcode }} {{ $order->shippingAddress->city }}</p>
                        <p>{{ $order->shippingAddress->country?->native }}</p>
                    </address>
                </x-ui.card>
            @endif

            <x-ui.card padding="md">
                <h3 class="font-semibold text-neutral-800 mb-3">Paiement & livraison</h3>
                <dl class="text-sm text-neutral-600 space-y-2">
                    @if ($order->transactions->isNotEmpty())
                        @php($tx = $order->transactions->where('type', 'capture')->first() ?? $order->transactions->first())
                        <div>
                            <dt class="font-medium text-neutral-800">Mode de paiement</dt>
                            <dd class="mt-0.5 capitalize">{{ $tx->driver ?? '—' }}</dd>
                        </div>
                        @if ($tx->reference)
                            <div>
                                <dt class="font-medium text-neutral-800">Référence paiement</dt>
                                <dd class="mt-0.5 font-mono text-xs text-neutral-500">{{ $tx->reference }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="font-medium text-neutral-800">Statut paiement</dt>
                            <dd class="mt-0.5">{{ $tx->status ?? '—' }}</dd>
                        </div>
                    @endif
                    @if ($order->shippingAddress?->shipping_option)
                        <div>
                            <dt class="font-medium text-neutral-800">Mode de livraison</dt>
                            <dd class="mt-0.5">{{ $order->shippingAddress->shipping_option }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-center gap-3">
            <x-ui.button variant="primary" size="lg" href="{{ url('/') }}">Retour à l'accueil</x-ui.button>
            @auth
                <x-ui.button variant="secondary" size="lg" href="/compte/commandes">Mes commandes</x-ui.button>
            @endauth
        </div>
    </div>
</section>

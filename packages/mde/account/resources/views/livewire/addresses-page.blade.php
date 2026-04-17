<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-neutral-900">Mes adresses</h1>
            <p class="text-neutral-600 mt-1 text-sm">Adresses de facturation et livraison enregistrées.</p>
        </div>
        <x-ui.button variant="primary" icon="plus" disabled title="Fonctionnalité bientôt disponible">Ajouter</x-ui.button>
    </div>

    @if ($addresses->isEmpty())
        <x-ui.card padding="lg">
            <p class="text-center text-neutral-500 py-6">Aucune adresse enregistrée. Elles seront créées automatiquement lors de votre première commande.</p>
        </x-ui.card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach ($addresses as $address)
                <x-ui.card padding="lg">
                    <div class="flex items-start justify-between mb-2">
                        <x-ui.badge :variant="$address->type === 'billing' ? 'primary' : 'neutral'">
                            {{ $address->type === 'billing' ? 'Facturation' : 'Livraison' }}
                        </x-ui.badge>
                        @if ($address->shipping_default || $address->billing_default)
                            <x-ui.badge variant="success">Par défaut</x-ui.badge>
                        @endif
                    </div>
                    <address class="not-italic text-sm text-neutral-700 leading-relaxed">
                        <p class="font-semibold text-neutral-900">{{ $address->first_name }} {{ $address->last_name }}</p>
                        @if ($address->company_name)<p>{{ $address->company_name }}</p>@endif
                        <p>{{ $address->line_one }}</p>
                        @if ($address->line_two)<p>{{ $address->line_two }}</p>@endif
                        <p>{{ $address->postcode }} {{ $address->city }}</p>
                        @if ($address->contact_phone)<p class="mt-1">{{ $address->contact_phone }}</p>@endif
                    </address>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>

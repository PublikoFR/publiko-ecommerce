<div>
    <h1 class="text-2xl md:text-3xl font-black text-neutral-900 mb-2">Bonjour {{ $user?->name }}</h1>
    <p class="text-neutral-600 mb-8">Bienvenue dans votre espace {{ $customer?->company_name ?? 'Pro' }}.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        <x-ui.card class="text-center" padding="lg">
            <x-ui.icon name="shopping-bag" class="w-10 h-10 text-primary-600 mx-auto mb-3" />
            <p class="text-3xl font-black text-neutral-900">{{ $recentOrders->count() }}</p>
            <p class="text-sm text-neutral-500 mt-1">Commandes récentes</p>
            <x-ui.button variant="link" size="sm" href="{{ route('account.orders') }}" class="mt-3">Voir tout →</x-ui.button>
        </x-ui.card>

        <x-ui.card class="text-center" padding="lg">
            <x-ui.icon name="list" class="w-10 h-10 text-primary-600 mx-auto mb-3" />
            <p class="text-3xl font-black text-neutral-900">—</p>
            <p class="text-sm text-neutral-500 mt-1">Listes d'achat</p>
            <x-ui.button variant="link" size="sm" href="/compte/listes-achat" class="mt-3">Voir tout →</x-ui.button>
        </x-ui.card>

        <x-ui.card class="text-center" padding="lg">
            <x-ui.icon name="check" class="w-10 h-10 text-success-600 mx-auto mb-3" />
            <p class="text-3xl font-black text-neutral-900">—</p>
            <p class="text-sm text-neutral-500 mt-1">Points fidélité</p>
            <x-ui.button variant="link" size="sm" href="{{ route('account.loyalty') }}" class="mt-3">Voir le programme →</x-ui.button>
        </x-ui.card>
    </div>

    <x-ui.card padding="lg">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-neutral-900">Commandes récentes</h2>
            <x-ui.button variant="link" size="sm" href="{{ route('account.orders') }}">Voir toutes</x-ui.button>
        </div>
        @if ($recentOrders->isEmpty())
            <p class="text-sm text-neutral-500 text-center py-8">Vous n'avez pas encore passé de commande.</p>
        @else
            <ul class="divide-y divide-neutral-100">
                @foreach ($recentOrders as $order)
                    <li class="py-3 flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-neutral-900">#{{ $order->reference ?? $order->id }}</p>
                            <p class="text-xs text-neutral-500">{{ optional($order->placed_at)->format('d/m/Y') }} · {{ $order->status }}</p>
                        </div>
                        <a href="{{ route('account.order.view', $order->id) }}" class="text-sm text-primary-600 font-semibold hover:text-primary-700">Détails →</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>

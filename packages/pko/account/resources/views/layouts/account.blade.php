@php
$navItems = [
    ['label' => 'Tableau de bord', 'route' => 'account.dashboard', 'icon' => 'user'],
    ['label' => 'Mes commandes', 'route' => 'account.orders', 'icon' => 'shopping-bag'],
    ['label' => 'Mes listes d\'achat', 'route' => 'account.purchase-lists.index', 'icon' => 'list'],
    ['label' => 'Mes adresses', 'route' => 'account.addresses', 'icon' => 'map-pin'],
    ['label' => 'Ma société', 'route' => 'account.company', 'icon' => 'users'],
    ['label' => 'Mon profil', 'route' => 'account.profile', 'icon' => 'user'],
    ['label' => 'Programme fidélité', 'route' => 'account.loyalty', 'icon' => 'check'],
    ['label' => 'Mes factures', 'route' => 'account.invoices', 'icon' => 'credit-card'],
];

$current = request()->route()?->getName();
$user = auth()->user();
$customer = $user?->customers()->first();
@endphp

<x-layout.storefront>
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-8">
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <x-ui.card padding="md" class="lg:mb-0 mb-4">
                    <div class="pb-4 mb-4 border-b border-neutral-100">
                        <p class="text-xs uppercase tracking-wider text-neutral-500 font-semibold">Connecté·e en tant que</p>
                        <p class="mt-1 font-bold text-neutral-900">{{ $customer?->company_name ?? $user?->name }}</p>
                        <p class="text-xs text-neutral-500 truncate">{{ $user?->email }}</p>
                    </div>

                    <nav class="space-y-1">
                        @foreach ($navItems as $item)
                            @php
                                $href = $item['href'] ?? (\Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#');
                                $active = $current === $item['route'];
                            @endphp
                            <a href="{{ $href }}" @if (empty($item['href'])) wire:navigate @endif class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition {{ $active ? 'bg-primary-50 text-primary-700' : 'text-neutral-700 hover:bg-neutral-50 hover:text-primary-700' }}">
                                <x-ui.icon :name="$item['icon']" class="w-4 h-4 {{ $active ? 'text-primary-600' : 'text-neutral-400' }}" />
                                {{ $item['label'] }}
                            </a>
                        @endforeach

                        <form method="POST" action="/deconnexion" class="pt-3 mt-3 border-t border-neutral-100">@csrf
                            <button type="submit" class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium w-full text-left text-neutral-700 hover:bg-danger-50 hover:text-danger-700 transition">
                                <x-ui.icon name="logout" class="w-4 h-4 text-neutral-400" />
                                Se déconnecter
                            </button>
                        </form>
                    </nav>
                </x-ui.card>
            </aside>

            <div>
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layout.storefront>

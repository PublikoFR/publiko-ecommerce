<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-black text-neutral-900">Mes commandes</h1>
        <p class="text-neutral-600 mt-1 text-sm">Historique complet de vos commandes.</p>
    </div>

    <x-ui.card padding="none">
        @php $orders = $this->orders; @endphp
        @if ($orders->isEmpty())
            <p class="text-center text-neutral-500 py-16">Aucune commande pour l'instant.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200">
                    <thead class="bg-neutral-50">
                        <tr class="text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                            <th class="px-4 py-3">Référence</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 text-sm">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-3 font-mono font-semibold text-neutral-900">#{{ $order->reference ?? $order->id }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ optional($order->placed_at)->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge variant="primary">{{ $order->status }}</x-ui.badge>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-neutral-900">
                                    {{ $order->total?->formatted() ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('account.order.view', $order->id) }}" class="text-primary-600 font-semibold hover:text-primary-700">Détails →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-neutral-200">{{ $orders->links() }}</div>
        @endif
    </x-ui.card>
</div>

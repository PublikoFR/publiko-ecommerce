<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-display font-bold text-neutral-900">Mes factures</h1>
        <p class="text-neutral-600 mt-1 text-sm">Retrouvez vos factures et vos avoirs, à télécharger au format PDF.</p>
    </div>

    <x-ui.card padding="none">
        @php $invoices = $this->invoices; @endphp
        @if ($invoices->isEmpty())
            <div class="px-6 py-16 text-center">
                <x-ui.icon name="file-text" class="w-10 h-10 text-primary-600 mx-auto mb-3" />
                <p class="font-semibold text-neutral-900">Aucune facture disponible pour le moment</p>
                <p class="mt-2 text-sm text-neutral-600">Vos factures et vos avoirs apparaîtront ici dès leur émission.</p>
            </div>
        @else
            <div class="overflow-x-auto" role="region" aria-label="Factures et avoirs" tabindex="0">
                <table class="min-w-full divide-y divide-neutral-200">
                    <caption class="sr-only">Factures et avoirs de vos commandes</caption>
                    <thead class="bg-neutral-50 text-left text-xs font-semibold text-neutral-600">
                        <tr>
                            <th scope="col" class="px-4 py-3">Date</th>
                            <th scope="col" class="px-4 py-3">Numéro</th>
                            <th scope="col" class="px-4 py-3">Commande</th>
                            <th scope="col" class="px-4 py-3">Type</th>
                            <th scope="col" class="px-4 py-3 text-right">Montant TTC</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Document</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 text-sm">
                        @foreach ($invoices as $invoice)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-3 whitespace-nowrap text-neutral-600">{{ \Illuminate\Support\Carbon::parse($invoice['date'])->format('d/m/Y') }}</td>
                                <th scope="row" class="px-4 py-3 font-mono text-left text-neutral-900">{{ $invoice['number'] }}</th>
                                <td class="px-4 py-3"><a href="{{ $invoice['order_url'] }}" class="text-primary-600 underline underline-offset-2">#{{ $invoice['order_reference'] }}</a></td>
                                <td class="px-4 py-3"><x-ui.badge variant="primary">{{ $invoice['type'] }}</x-ui.badge></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-mono text-neutral-900">{{ $invoice['total'] }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ $invoice['download_url'] }}" class="inline-flex items-center gap-2 whitespace-nowrap text-primary-600 font-semibold hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4" aria-label="Télécharger {{ $invoice['type'] }} {{ $invoice['number'] }} en PDF">
                                        <x-ui.icon name="download" class="w-4 h-4" /> PDF
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())
                <div class="px-4 py-3 border-t border-neutral-200">{{ $invoices->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>

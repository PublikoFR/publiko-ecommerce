<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-black text-neutral-900">Ma société</h1>
        <p class="text-neutral-600 mt-1 text-sm">Informations légales validées via la base INSEE.</p>
    </div>

    @if ($customer === null)
        <x-ui.alert variant="warning">Aucune société rattachée à votre compte.</x-ui.alert>
    @else
        <x-ui.card padding="lg">
            <dl class="divide-y divide-neutral-100 text-sm">
                <div class="py-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <dt class="font-semibold text-neutral-500">Raison sociale</dt>
                    <dd class="sm:col-span-2 font-medium text-neutral-900">{{ $customer->company_name ?? '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <dt class="font-semibold text-neutral-500">SIRET</dt>
                    <dd class="sm:col-span-2 font-mono text-neutral-900">{{ $customer->meta['siret'] ?? '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <dt class="font-semibold text-neutral-500">TVA intra-communautaire</dt>
                    <dd class="sm:col-span-2 font-mono text-neutral-900">{{ $customer->tax_identifier ?? '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <dt class="font-semibold text-neutral-500">Code NAF</dt>
                    <dd class="sm:col-span-2 text-neutral-900">{{ $customer->naf_code ?? '—' }}</dd>
                </div>
                <div class="py-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <dt class="font-semibold text-neutral-500">Statut INSEE</dt>
                    <dd class="sm:col-span-2">
                        @php $status = $customer->sirene_status; @endphp
                        @if ($status === 'active')
                            <x-ui.badge variant="success">Actif — vérifié</x-ui.badge>
                        @elseif ($status === 'pending')
                            <x-ui.badge variant="warning">En cours de vérification</x-ui.badge>
                        @elseif ($status === 'inactive')
                            <x-ui.badge variant="danger">Inactif</x-ui.badge>
                        @else
                            <span class="text-neutral-500">—</span>
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="mt-6 pt-5 border-t border-neutral-100 text-sm text-neutral-500">
                Pour toute modification, <a href="/pages/nous-contacter" class="text-primary-600 font-semibold hover:underline">contactez votre conseiller MDE</a>.
            </div>
        </x-ui.card>
    @endif
</div>

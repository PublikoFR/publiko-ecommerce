{{-- Configurations enregistrées — table inline reproduisant le panneau PrestaShop.
     Requête volontairement dans la vue pour refléter en direct duplications /
     suppressions déclenchées par les actions wire:click de la page. --}}
@php($configs = \Pko\AiImporter\Models\ImporterConfig::query()->orderBy('name')->get())
<div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
    <table class="w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
        <thead>
            <tr class="bg-gray-50 dark:bg-white/5">
                <th class="px-3 py-2 text-start text-sm font-semibold text-gray-950 dark:text-white">Fournisseur</th>
                <th class="px-3 py-2 text-start text-sm font-semibold text-gray-950 dark:text-white">Type</th>
                <th class="px-3 py-2 text-center text-sm font-semibold text-gray-950 dark:text-white">Colonnes</th>
                <th class="px-3 py-2 text-end text-sm font-semibold text-gray-950 dark:text-white">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
            @forelse ($configs as $config)
                <tr class="hover:bg-gray-50 dark:hover:bg-white/5" wire:key="cfg-{{ $config->id }}">
                    <td class="px-3 py-2 text-sm">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $config->name }}</span>
                        @if ($config->supplier_name)
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $config->supplier_name }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-sm">
                        <span class="fi-badge inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400">
                            {{ data_get($config->config_data, 'type', data_get($config->config_data, 'primary_sheet', '—')) }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-center text-sm text-gray-700 dark:text-gray-300">
                        {{ count((array) data_get($config->config_data, 'mapping', [])) ?: '—' }}
                    </td>
                    <td class="px-3 py-2 text-end">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ \Pko\AiImporter\Filament\Resources\ImporterConfigResource::getUrl('edit', ['record' => $config]) }}"
                               class="fi-icon-btn rounded-lg p-1.5 text-gray-400 hover:text-primary-600 hover:bg-gray-100 dark:hover:bg-white/5"
                               title="Éditer">
                                <x-filament::icon icon="heroicon-o-pencil-square" class="h-5 w-5" />
                            </a>
                            <button type="button"
                                    wire:click="duplicateImporterConfig({{ $config->id }})"
                                    class="fi-icon-btn rounded-lg p-1.5 text-gray-400 hover:text-info-600 hover:bg-gray-100 dark:hover:bg-white/5"
                                    title="Dupliquer">
                                <x-filament::icon icon="heroicon-o-document-duplicate" class="h-5 w-5" />
                            </button>
                            <button type="button"
                                    wire:click="deleteImporterConfig({{ $config->id }})"
                                    wire:confirm="Supprimer la configuration « {{ $config->name }} » ?"
                                    class="fi-icon-btn rounded-lg p-1.5 text-gray-400 hover:text-danger-600 hover:bg-gray-100 dark:hover:bg-white/5"
                                    title="Supprimer">
                                <x-filament::icon icon="heroicon-o-trash" class="h-5 w-5" />
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        Aucune configuration. Créez-en une ou importez un JSON pour démarrer.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-neutral-900/60" wire:click="$set('open', false)"></div>

            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-neutral-200 shrink-0">
                    <div>
                        <h2 class="text-lg font-display font-bold text-neutral-900">Ajouter à une liste</h2>
                        <p class="text-xs text-neutral-500 mt-0.5">Choisissez une liste existante ou créez-en une nouvelle.</p>
                    </div>
                    <button type="button" class="text-neutral-400 hover:text-neutral-600" wire:click="$set('open', false)">
                        <x-ui.icon name="close" class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-5 overflow-y-auto">
                    @if ($flash)<x-ui.alert variant="success">{{ $flash }}</x-ui.alert>@endif

                    {{-- Création : même bloc que la page « Mes listes d'achat » du compte client --}}
                    <x-ui.card padding="lg">
                        <form wire:submit="createAndAdd" class="flex gap-3">
                            <div class="flex-1">
                                <x-ui.input wire:model="newListName" placeholder="Nom de la nouvelle liste (ex : Chantier Dupont)" :error="$errors->first('newListName')" />
                            </div>
                            <x-ui.button type="submit" variant="primary" icon="plus">Créer</x-ui.button>
                        </form>
                    </x-ui.card>

                    {{-- Listes existantes : même présentation en cartes que le back-office client --}}
                    @if ($lists->isEmpty())
                        <x-ui.card padding="lg" class="text-center">
                            <x-ui.icon name="list" class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
                            <p class="text-neutral-500">Aucune liste pour le moment.</p>
                        </x-ui.card>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($lists as $list)
                                <button type="button" wire:click="addToExisting({{ $list->id }})" class="text-left group/list">
                                    <x-ui.card padding="lg" hover class="h-full group-hover/list:border-primary-300">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <h3 class="font-bold text-neutral-900 truncate group-hover/list:text-primary-700 transition">{{ $list->name }}</h3>
                                                <p class="text-xs text-neutral-500">{{ $list->items_count }} articles</p>
                                            </div>
                                            <span class="shrink-0 w-7 h-7 flex items-center justify-center rounded-md bg-primary-50 text-primary-600 group-hover/list:bg-primary-600 group-hover/list:text-white transition">
                                                <x-ui.icon name="plus" class="w-4 h-4" />
                                            </span>
                                        </div>
                                    </x-ui.card>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

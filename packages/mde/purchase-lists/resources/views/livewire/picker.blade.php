<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-neutral-900/60" wire:click="$set('open', false)"></div>
            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md">
                <div class="flex items-center justify-between px-6 py-4 border-b border-neutral-200">
                    <h2 class="text-lg font-semibold text-neutral-900">Ajouter à une liste</h2>
                    <button type="button" class="text-neutral-400 hover:text-neutral-600" wire:click="$set('open', false)"><x-ui.icon name="close" class="w-5 h-5" /></button>
                </div>
                <div class="p-6 space-y-4">
                    @if ($flash)<x-ui.alert variant="success">{{ $flash }}</x-ui.alert>@endif

                    @if ($lists->isEmpty())
                        <p class="text-sm text-neutral-500">Aucune liste. Créez-en une :</p>
                    @else
                        <div class="space-y-2 max-h-60 overflow-y-auto">
                            @foreach ($lists as $list)
                                <button type="button" wire:click="addToExisting({{ $list->id }})" class="w-full text-left px-4 py-2 rounded-md hover:bg-primary-50 hover:text-primary-700 flex items-center justify-between transition">
                                    <span class="font-semibold">{{ $list->name }}</span>
                                    <span class="text-xs text-neutral-400">{{ $list->items_count }}</span>
                                </button>
                            @endforeach
                        </div>
                        <div class="pt-3 border-t border-neutral-100"><p class="text-xs text-neutral-500 uppercase tracking-wide font-semibold mb-2">Ou créer une nouvelle liste</p></div>
                    @endif

                    <form wire:submit="createAndAdd" class="flex gap-2">
                        <input type="text" wire:model="newListName" placeholder="Nom de la liste" class="flex-1 rounded-md border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500" />
                        <x-ui.button type="submit" variant="primary" size="sm">Créer</x-ui.button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

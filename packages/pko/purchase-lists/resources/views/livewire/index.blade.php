<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-neutral-900">Mes listes d'achat</h1>
            <p class="text-neutral-600 mt-1 text-sm">Organisez vos fréquences d'achat en listes réutilisables.</p>
        </div>
    </div>

    <x-ui.card padding="lg">
        <form wire:submit="createList" class="flex gap-3">
            <div class="flex-1">
                <x-ui.input wire:model="newListName" placeholder="Nom de la nouvelle liste (ex : Chantier Dupont)" :error="$errors->first('newListName')" />
            </div>
            <x-ui.button type="submit" variant="primary" icon="plus">Créer</x-ui.button>
        </form>
    </x-ui.card>

    @if ($lists->isEmpty())
        <x-ui.card padding="lg" class="text-center">
            <x-ui.icon name="list" class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
            <p class="text-neutral-500">Aucune liste pour le moment.</p>
        </x-ui.card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach ($lists as $list)
                <x-ui.card padding="lg" hover>
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <h3 class="font-bold text-neutral-900">
                                <a href="{{ route('account.purchase-lists.show', $list->id) }}" class="hover:text-primary-700">{{ $list->name }}</a>
                            </h3>
                            <p class="text-xs text-neutral-500">{{ $list->items_count }} articles · {{ optional($list->updated_at)->diffForHumans() }}</p>
                        </div>
                        <button wire:click="deleteList({{ $list->id }})" wire:confirm="Supprimer cette liste ?" class="text-neutral-400 hover:text-danger-600 transition" title="Supprimer"><x-ui.icon name="trash" class="w-4 h-4" /></button>
                    </div>
                    <x-ui.button variant="outline" size="sm" :href="route('account.purchase-lists.show', $list->id)" class="w-full justify-center mt-3">Ouvrir</x-ui.button>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>

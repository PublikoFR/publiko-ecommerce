<div class="space-y-6">
    <div>
        <a href="{{ route('account.purchase-lists.index') }}" class="text-sm text-primary-600 hover:text-primary-700 font-semibold" wire:navigate>← Mes listes</a>
        <h1 class="text-2xl font-black text-neutral-900 mt-1">{{ $list->name }}</h1>
    </div>

    @if (session('status'))
        <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.card padding="lg">
        <form wire:submit="save" class="space-y-4">
            <x-ui.input wire:model="name" label="Nom de la liste" :error="$errors->first('name')" />
            <x-ui.textarea wire:model="notes" label="Notes" rows="3">{{ $list->notes }}</x-ui.textarea>
            <div class="flex justify-end"><x-ui.button type="submit" variant="primary" size="sm">Enregistrer</x-ui.button></div>
        </form>
    </x-ui.card>

    <x-ui.card padding="none">
        <div class="flex items-center justify-between px-5 py-4 border-b border-neutral-100">
            <h2 class="font-bold text-neutral-900">Articles ({{ $list->items->count() }})</h2>
            @if ($list->items->isNotEmpty())
                <x-ui.button wire:click="addAllToCart" variant="primary" size="sm" icon="cart">Tout ajouter au panier</x-ui.button>
            @endif
        </div>
        @if ($list->items->isEmpty())
            <p class="text-center text-neutral-500 py-10">Liste vide. Ajoutez des produits depuis le catalogue.</p>
        @else
            <ul class="divide-y divide-neutral-100">
                @foreach ($list->items as $item)
                    <li class="p-5 flex items-center gap-4">
                        <div class="flex-1">
                            <p class="font-semibold text-neutral-900">{{ $item->purchasable?->getDescription() ?? '(produit indisponible)' }}</p>
                            <p class="text-xs text-neutral-500">{{ $item->purchasable?->getIdentifier() }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="w-8 h-8 border border-neutral-300 rounded text-neutral-600 hover:border-primary-500 hover:text-primary-600" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})"><x-ui.icon name="minus" class="w-3 h-3 mx-auto" /></button>
                            <span class="w-10 text-center font-semibold">{{ $item->quantity }}</span>
                            <button type="button" class="w-8 h-8 border border-neutral-300 rounded text-neutral-600 hover:border-primary-500 hover:text-primary-600" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"><x-ui.icon name="plus" class="w-3 h-3 mx-auto" /></button>
                            <button type="button" class="ml-3 text-neutral-400 hover:text-danger-600" wire:click="removeItem({{ $item->id }})"><x-ui.icon name="trash" class="w-4 h-4" /></button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>

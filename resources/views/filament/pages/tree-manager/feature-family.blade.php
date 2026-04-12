@php($hasValues = count($family['values'] ?? []) > 0)
<li data-id="{{ $family['id'] }}">
    <div class="tree-node">
        <x-heroicon-o-bars-3 class="tree-node__handle h-4 w-4" />
        @if ($hasValues)
            <button type="button" class="tree-node__toggle" x-on:click.stop="toggleNode($el)">
                <x-heroicon-o-chevron-right class="h-3.5 w-3.5" />
            </button>
        @endif
        <x-heroicon-o-tag class="h-4 w-4 flex-shrink-0 text-gray-400" />
        <span class="tree-node__label">
            {{ $family['name'] }}
            <span class="ml-1 font-mono text-xs text-gray-400">{{ $family['handle'] }}</span>
            @if ($family['multi_value'])
                <span class="tree-node__badge" title="Multi-valeurs">multi</span>
            @endif
            @if ($family['searchable'])
                <span class="tree-node__badge" title="Indexée pour la recherche">idx</span>
            @endif
            <span class="tree-node__badge">{{ count($family['values'] ?? []) }}</span>
        </span>
        <div class="tree-node__actions">
            <button type="button"
                    class="tree-node__action"
                    title="Ajouter une valeur"
                    wire:click="mountAction('createValueAction', { family_id: {{ $family['id'] }} })">
                <x-heroicon-o-plus class="h-4 w-4" />
            </button>
            <button type="button"
                    class="tree-node__action"
                    title="Modifier la famille"
                    wire:click="mountAction('editFamilyAction', { id: {{ $family['id'] }} })">
                <x-heroicon-o-pencil-square class="h-4 w-4" />
            </button>
            <button type="button"
                    class="tree-node__action tree-node__action--danger"
                    title="Supprimer la famille"
                    wire:click="mountAction('deleteFamilyAction', { id: {{ $family['id'] }} })">
                <x-heroicon-o-trash class="h-4 w-4" />
            </button>
        </div>
    </div>
    @if ($hasValues)
    <ul class="tree-children"
        data-sortable="values"
        data-family-id="{{ $family['id'] }}">
        @foreach ($family['values'] as $value)
            <li data-id="{{ $value['id'] }}">
                <div class="tree-node">
                    <x-heroicon-o-bars-3 class="tree-node__handle h-4 w-4" />
                    <x-heroicon-o-tag class="h-4 w-4 flex-shrink-0 text-gray-400" />
                    <span class="tree-node__label">
                        {{ $value['name'] }}
                        <span class="ml-1 font-mono text-xs text-gray-400">{{ $value['handle'] }}</span>
                    </span>
                    <div class="tree-node__actions">
                        <button type="button"
                                class="tree-node__action"
                                title="Modifier"
                                wire:click="mountAction('editValueAction', { id: {{ $value['id'] }} })">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                        </button>
                        <button type="button"
                                class="tree-node__action tree-node__action--danger"
                                title="Supprimer"
                                wire:click="mountAction('deleteValueAction', { id: {{ $value['id'] }} })">
                            <x-heroicon-o-trash class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
    @endif
</li>

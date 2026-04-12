@php($children = $node['children'] ?? [])
@php($hasChildren = count($children) > 0)
<li data-id="{{ $node['id'] }}">
    <div class="tree-node">
        <x-heroicon-o-bars-3 class="tree-node__handle h-4 w-4" />
        @if ($hasChildren)
            <button type="button" class="tree-node__toggle" x-on:click.stop="toggleNode($el)">
                <x-heroicon-o-chevron-right class="h-3.5 w-3.5" />
            </button>
        @endif
        <x-heroicon-o-folder class="h-4 w-4 flex-shrink-0 text-gray-400" />
        <span class="tree-node__label">
            {{ $node['name'] }}
            <span class="tree-node__badge">{{ $node['product_count'] }}</span>
        </span>
        <div class="tree-node__actions">
            <button type="button"
                    class="tree-node__action"
                    title="Ajouter une sous-catégorie"
                    wire:click="mountAction('createCollectionAction', { parent_id: {{ $node['id'] }} })">
                <x-heroicon-o-plus class="h-4 w-4" />
            </button>
            <button type="button"
                    class="tree-node__action"
                    title="Modifier"
                    wire:click="mountAction('editCollectionAction', { id: {{ $node['id'] }} })">
                <x-heroicon-o-pencil-square class="h-4 w-4" />
            </button>
            <button type="button"
                    class="tree-node__action tree-node__action--danger"
                    title="Supprimer"
                    wire:click="mountAction('deleteCollectionAction', { id: {{ $node['id'] }} })">
                <x-heroicon-o-trash class="h-4 w-4" />
            </button>
        </div>
    </div>
    @if ($hasChildren)
    <ul class="tree-children"
        data-sortable="collection-children"
        data-parent-id="{{ $node['id'] }}">
        @foreach ($children as $child)
            @include('filament.pages.tree-manager.collection-node', ['node' => $child, 'depth' => $depth + 1])
        @endforeach
    </ul>
    @endif
</li>

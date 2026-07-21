@php($children = $node['children'] ?? [])
@php($hasChildren = count($children) > 0)
@php($enabled = $node['pko_enabled'] ?? true)
@php($browseChildren = $node['pko_browse_children'] ?? false)
<li data-id="{{ $node['id'] }}" class="{{ $enabled ? '' : 'opacity-50' }}">
    <div class="tree-node">
        <input type="checkbox"
               class="tree-node__select h-4 w-4 flex-shrink-0 rounded border-gray-300 text-primary-600"
               title="Sélectionner pour l'export"
               x-model="exportSelected[{{ $node['id'] }}]"
               x-on:click.stop />
        <x-heroicon-o-bars-3 class="tree-node__handle h-4 w-4" />
        @if ($hasChildren)
            <button type="button" class="tree-node__toggle" x-on:click.stop="toggleNode($el)">
                <x-heroicon-o-chevron-right class="h-3.5 w-3.5" />
            </button>
        @endif
        {{-- Vignette si la catégorie a une image, sinon picto dossier : permet de
             repérer d'un coup d'œil les catégories sans visuel. --}}
        @if (filled($node['image_url'] ?? null))
            <img src="{{ $node['image_url'] }}"
                 alt=""
                 loading="lazy"
                 class="tree-node__thumb h-4 w-4 flex-shrink-0" />
        @else
            <x-heroicon-o-folder class="h-4 w-4 flex-shrink-0 {{ $enabled ? 'text-gray-400' : 'text-red-300' }}" />
        @endif
        <span class="tree-node__label">
            {{ $node['name'] }}
            @if (! $enabled)
                <span class="tree-node__badge tree-node__badge--disabled">désactivée</span>
            @endif
            @if ($browseChildren && $hasChildren)
                <span class="tree-node__badge tree-node__badge--browse" title="Affiche ses sous-catégories, pas de produits">listing catégories</span>
            @endif
            <span class="tree-node__badge">{{ $node['product_count'] }}</span>
        </span>
        {{-- Une seule entrée (engrenage) qui déplie un menu au survol : 4 pictos ×
             ~500 lignes saturaient visuellement l'arbre. Les libellés lèvent
             l'ambiguïté des icônes (l'œil se lisait « voir » et non « masquer »).
             `is-open` force la visibilité : le menu déborde de `.tree-node`, donc
             `.tree-node:hover` ne suffit pas à le garder affiché. --}}
        <div class="tree-node__actions"
             x-data="{ open: false }"
             :class="{ 'is-open': open }"
             x-on:mouseenter="open = true"
             x-on:mouseleave="open = false">
            <button type="button"
                    class="tree-node__action"
                    aria-haspopup="true"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-label="Actions sur la catégorie"
                    x-on:click.stop="open = ! open">
                <x-heroicon-o-cog-6-tooth class="h-4 w-4" />
            </button>

            <div class="tree-node__menu"
                 x-show="open"
                 x-cloak
                 x-transition.opacity.duration.100ms
                 x-on:click.outside="open = false">
                {{-- L'icône montre l'ACTION, pas l'état : œil barré = cliquer pour
                     masquer. L'état reste lisible via la ligne grisée + le badge. --}}
                <button type="button"
                        class="tree-node__menu-item"
                        wire:click="toggleCollectionEnabled({{ $node['id'] }})">
                    @if ($enabled)
                        <x-heroicon-o-eye-slash class="h-4 w-4 flex-shrink-0" />
                        <span>Désactiver la catégorie</span>
                    @else
                        <x-heroicon-o-eye class="h-4 w-4 flex-shrink-0" />
                        <span>Activer la catégorie</span>
                    @endif
                </button>
                {{-- Cascade sur toute la branche : le libellé le dit, sinon on
                     croit n'agir que sur la ligne cliquée. --}}
                <button type="button"
                        class="tree-node__menu-item"
                        wire:click="toggleCollectionBrowseChildren({{ $node['id'] }})">
                    @if ($browseChildren)
                        <x-heroicon-o-shopping-bag class="h-4 w-4 flex-shrink-0" />
                        <span>Revenir au listing produits<br><small>toute la branche</small></span>
                    @else
                        <x-heroicon-o-squares-2x2 class="h-4 w-4 flex-shrink-0" />
                        <span>Page de listing de catégories<br><small>toute la branche</small></span>
                    @endif
                </button>
                <button type="button"
                        class="tree-node__menu-item"
                        wire:click="mountAction('createCollectionAction', { parent_id: {{ $node['id'] }} })">
                    <x-heroicon-o-plus class="h-4 w-4 flex-shrink-0" />
                    <span>Ajouter une sous-catégorie</span>
                </button>
                <button type="button"
                        class="tree-node__menu-item"
                        wire:click="mountAction('editCollectionAction', { id: {{ $node['id'] }} })">
                    <x-heroicon-o-pencil-square class="h-4 w-4 flex-shrink-0" />
                    <span>Modifier la catégorie</span>
                </button>
                <button type="button"
                        class="tree-node__menu-item tree-node__menu-item--danger"
                        wire:click="mountAction('deleteCollectionAction', { id: {{ $node['id'] }} })">
                    <x-heroicon-o-trash class="h-4 w-4 flex-shrink-0" />
                    <span>Supprimer la catégorie</span>
                </button>
            </div>
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

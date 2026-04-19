@php
    $partial = 'filament.resources.pko-product.partials';
@endphp

<x-filament-panels::page>
    @assets
        <script>
            (function () {
                if (window.__pkoProductDirtyGuardInstalled) return;
                window.__pkoProductDirtyGuardInstalled = true;
                window.addEventListener('beforeunload', function (e) {
                    const el = document.querySelector('[data-pko-product-edit]');
                    if (!el) return;
                    const wireEl = el.closest('[wire\\:id]');
                    const wireId = wireEl && wireEl.getAttribute('wire:id');
                    const comp = wireId && window.Livewire ? window.Livewire.find(wireId) : null;
                    if (comp && comp.get('isDirty')) {
                        e.preventDefault();
                        e.returnValue = '';
                        return '';
                    }
                });
            })();

            window.pkoProductEditor = window.pkoProductEditor || {
                editorStatePath: 'descriptionData.longDesc',
                insertFromMedia() {
                    window.Livewire.dispatch('open-media-picker-modal', {
                        statePath: '__pko_product_tiptap',
                        multiple: false,
                        preselected: [],
                        mediagroup: 'product',
                        folder: 'products',
                    });
                },
                _insertTiptapImage(media) {
                    // On passe l'ID du média en `id` — le package TipTap le reporte
                    // sur l'attribut `data-id` de l'image. Base pour la traçabilité DB.
                    const evt = new CustomEvent('insert-content', {
                        bubble: true,
                        detail: {
                            statePath: this.editorStatePath,
                            type: 'media',
                            media: {
                                id: media.id,
                                url: media.url,
                                src: media.url,
                                alt: media.alt || '',
                                title: media.alt || '',
                            },
                        },
                    });
                    window.dispatchEvent(evt);
                },
            };
            if (!window.__pkoProductEditorBound) {
                window.__pkoProductEditorBound = true;
                window.addEventListener('media-picked', (e) => {
                    const data = (e.detail && (e.detail[0] ?? e.detail)) || {};
                    if (data.statePath !== '__pko_product_tiptap') return;
                    const media = (data.medias || [])[0];
                    if (!media) return;
                    window.pkoProductEditor._insertTiptapImage({
                        id: media.id,
                        url: media.url,
                        alt: media.alt || '',
                    });
                });
            }
        </script>
    @endassets
    <form
        wire:submit.prevent="save"
        data-pko-product-edit
        class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 pb-28"
    >

        {{-- ============================================================ --}}
        {{-- COLONNE PRINCIPALE --}}
        {{-- ============================================================ --}}
        <div class="space-y-4 min-w-0">

            {{-- 1. Informations générales --}}
            <x-pko-product::card title="Informations générales" icon="heroicon-o-information-circle">
                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Titre du produit *</label>
                    <input
                        type="text"
                        wire:model.blur="productName"
                        required
                        class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/15"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">SKU *</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-2 text-gray-500 bg-gray-50 dark:bg-white/5 border border-r-0 border-gray-300 dark:border-white/10 rounded-l text-sm">#</span>
                            <input type="text" wire:model.blur="sku" required class="flex-1 text-sm border border-gray-300 dark:border-white/10 rounded-r px-3 py-[7px] bg-white dark:bg-gray-900" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Code-barres EAN / UPC</label>
                        <input type="text" wire:model.blur="ean" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900" />
                    </div>
                </div>
                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Description courte</label>
                    <textarea wire:model.blur="shortDesc" rows="3" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-2 bg-white dark:bg-gray-900"></textarea>
                    <p class="text-xs text-gray-500 mt-1">Résumé affiché en tête de fiche et dans les listes.</p>
                </div>
            </x-pko-product::card>

            {{-- 2. Médias --}}
            <x-pko-product::card title="Médias" icon="heroicon-o-photo">
                {{ $this->mediaForm }}
            </x-pko-product::card>

            {{-- 3. Description longue --}}
            <x-pko-product::card title="Description longue" icon="heroicon-o-document-text">
                <div class="space-y-2">
                    {{-- Actions custom : médiathèque + URL externe. Le bouton HTML brut est le tool
                         natif `source` intégré dans la toolbar TipTap (icône </> en fin de ligne). --}}
                    <div class="flex gap-2 flex-wrap">
                        <x-filament::button
                            color="gray"
                            size="sm"
                            icon="heroicon-o-photo"
                            x-on:click="window.pkoProductEditor.insertFromMedia()"
                        >
                            Insérer une image (médiathèque ou URL)
                        </x-filament::button>
                    </div>

                    {{ $this->descriptionForm }}
                </div>
            </x-pko-product::card>

            {{-- 4. Caractéristiques techniques (CatalogFeatures) --}}
            <x-pko-product::card title="Caractéristiques techniques" icon="heroicon-o-list-bullet">
                @forelse ($this->featureFamilies as $family)
                    <div class="grid grid-cols-[180px_1fr] gap-3 items-center">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $family->name }}</div>
                        <div>
                            @if ($family->multi_value)
                                <select
                                    wire:model="featureValues.{{ $family->id }}"
                                    multiple
                                    class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                                >
                                    @foreach ($family->values as $value)
                                        <option value="{{ $value->id }}">{{ $value->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                <select
                                    wire:model="featureValues.{{ $family->id }}"
                                    class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                                >
                                    <option value="">—</option>
                                    @foreach ($family->values as $value)
                                        <option value="{{ $value->id }}">{{ $value->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500">Aucune famille de caractéristiques définie. Créez-en dans <strong>Catalogue → Caractéristiques</strong>.</p>
                @endforelse
            </x-pko-product::card>

            {{-- 5. Tarification --}}
            <x-pko-product::card title="Tarification" icon="heroicon-o-currency-euro">
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Prix TTC *</label>
                        <div class="relative">
                            <input type="text" wire:model.blur="price" required class="w-full text-sm font-mono border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 pr-7 text-right tabular-nums" />
                            <span class="absolute right-3 top-[9px] text-xs text-gray-500">€</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Prix comparatif (barré)</label>
                        <div class="relative">
                            <input type="text" wire:model.blur="comparePrice" class="w-full text-sm font-mono border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 pr-7 text-right tabular-nums" />
                            <span class="absolute right-3 top-[9px] text-xs text-gray-500">€</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Prix d'achat (coût)</label>
                        <div class="relative">
                            <input type="text" wire:model.blur="cost" class="w-full text-sm font-mono border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 pr-7 text-right tabular-nums" />
                            <span class="absolute right-3 top-[9px] text-xs text-gray-500">€</span>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Classe de taxe *</label>
                    <select wire:model="taxClassId" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-[7px] bg-white dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($this->taxClassOptions as $tax)
                            <option value="{{ $tax->id }}">{{ $tax->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Sous-card paliers B2B --}}
                <div class="border border-gray-200 dark:border-white/10 rounded-md mt-2">
                    <header class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                        <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-200">Tarification B2B (paliers par quantité)</h4>
                        <button type="button" wire:click="addTierPrice" class="text-xs text-primary-600 hover:text-primary-700">+ Ajouter un palier</button>
                    </header>
                    @if (count($tierPrices) > 0)
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-3 py-2 font-medium">Groupe client</th>
                                    <th class="px-3 py-2 font-medium">À partir de (u.)</th>
                                    <th class="px-3 py-2 font-medium text-right">Prix unitaire</th>
                                    <th class="px-3 py-2 font-medium text-right">—</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tierPrices as $index => $tier)
                                    <x-pko-product::tier-price-row :index="$index" :tier="$tier" />
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="px-3 py-4 text-xs text-gray-500">Aucun palier défini. Cliquez sur « Ajouter un palier » pour créer une remise quantitative.</p>
                    @endif
                </div>
            </x-pko-product::card>

            {{-- 6. Inventaire & expédition --}}
            <x-pko-product::card title="Inventaire & expédition" icon="heroicon-o-cube">
                <x-pko-product::switch-row label="Suivre le stock de ce produit" description="Décrémente automatiquement à chaque commande." model="trackStock" />
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Stock actuel</label>
                        <div class="relative">
                            <input type="number" wire:model.blur="stock" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 pr-8 text-right tabular-nums" />
                            <span class="absolute right-3 top-[9px] text-xs text-gray-500">u.</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Seuil d'alerte</label>
                        <input type="number" wire:model.blur="lowStockThreshold" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 text-right tabular-nums" />
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Stock de sécurité</label>
                        <input type="number" wire:model.blur="safetyStock" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 text-right tabular-nums" />
                    </div>
                </div>
                <x-pko-product::switch-row label="Autoriser les commandes en rupture" description="Les clients peuvent commander même quand le stock est à zéro." model="allowBackorder" />
                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Délai de réapprovisionnement</label>
                    <input type="text" wire:model.blur="leadTime" placeholder="ex: 3-5 jours ouvrés" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900" />
                </div>

                <hr class="border-gray-200 dark:border-white/10" />

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Poids (kg)</label>
                        <input type="text" wire:model.blur="weight" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 text-right tabular-nums" />
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Dimensions (L × l × H en cm)</label>
                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" wire:model.blur="length" placeholder="L" class="text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-[7px] bg-white dark:bg-gray-900 text-right tabular-nums" />
                            <input type="text" wire:model.blur="width" placeholder="l" class="text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-[7px] bg-white dark:bg-gray-900 text-right tabular-nums" />
                            <input type="text" wire:model.blur="height" placeholder="H" class="text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-[7px] bg-white dark:bg-gray-900 text-right tabular-nums" />
                        </div>
                    </div>
                </div>
            </x-pko-product::card>

            {{-- 7. Variantes --}}
            @php $variants = $this->variants; @endphp
            <x-pko-product::card title="Variantes" icon="heroicon-o-squares-2x2" :hint="$variants->total() . ' variante' . ($variants->total() > 1 ? 's' : '')">
                @if ($variants->total() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-500 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Variante</th>
                                    <th class="px-3 py-2 font-medium">SKU</th>
                                    <th class="px-3 py-2 font-medium text-right">Prix</th>
                                    <th class="px-3 py-2 font-medium">Stock</th>
                                    <th class="px-3 py-2 font-medium text-center">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($variants as $variant)
                                    <x-pko-product::variant-row :variant="$variant" />
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($variants->hasPages())
                        <div class="mt-3">{{ $variants->links() }}</div>
                    @endif
                @else
                    <p class="text-xs text-gray-500">Ce produit n'a pas encore de variante.</p>
                @endif
            </x-pko-product::card>

            {{-- 8. SEO --}}
            <x-pko-product::card title="Référencement (SEO)" icon="heroicon-o-magnifying-glass">
                <x-pko-product::google-preview />

                <div>
                    <label class="flex items-center justify-between text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <span>Titre SEO</span>
                        <span @class([
                            'text-xs tabular-nums',
                            'text-gray-500' => $this->seoTitleStatus === 'ok',
                            'text-warning-600' => $this->seoTitleStatus === 'warning',
                            'text-danger-600' => $this->seoTitleStatus === 'danger',
                        ])>{{ $this->seoTitleCount }} / 60</span>
                    </label>
                    <input type="text" wire:model.live="seoTitle" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900" />
                </div>

                <div>
                    <label class="flex items-center justify-between text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <span>Méta-description</span>
                        <span @class([
                            'text-xs tabular-nums',
                            'text-gray-500' => $this->seoDescStatus === 'ok',
                            'text-warning-600' => $this->seoDescStatus === 'warning',
                            'text-danger-600' => $this->seoDescStatus === 'danger',
                        ])>{{ $this->seoDescCount }} / 160</span>
                    </label>
                    <textarea wire:model.live="seoDesc" rows="3" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-2 bg-white dark:bg-gray-900"></textarea>
                </div>

                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">URL (slug)</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-2 text-xs text-gray-500 bg-gray-50 dark:bg-white/5 border border-r-0 border-gray-300 dark:border-white/10 rounded-l font-mono">
                            {{ parse_url(url('/'), PHP_URL_HOST) }}/produits/
                        </span>
                        <input type="text" value="{{ $productSlug }}" readonly class="flex-1 text-xs font-mono border border-gray-300 dark:border-white/10 rounded-r px-2 py-[7px] bg-gray-50 dark:bg-white/5 text-gray-600" />
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Généré automatiquement à partir de la marque, du nom et du MPN.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">URL canonique</label>
                        <input type="text" wire:model.blur="canonical" placeholder="Auto" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900" />
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Indexation</label>
                        <select wire:model="robots" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-[7px] bg-white dark:bg-gray-900">
                            <option value="index,follow">Indexer, suivre les liens</option>
                            <option value="noindex,follow">Ne pas indexer, suivre les liens</option>
                            <option value="noindex,nofollow">Ne pas indexer, ne pas suivre</option>
                        </select>
                    </div>
                </div>
            </x-pko-product::card>
        </div>

        {{-- ============================================================ --}}
        {{-- SIDEBAR DROITE (sticky) --}}
        {{-- ============================================================ --}}
        <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">

            {{-- Statut & visibilité --}}
            <x-pko-product::card>
                <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Statut &amp; visibilité</h3>
                        @if ($status === 'published')
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-success-700 bg-success-50 dark:bg-success-500/10 dark:text-success-400 px-2 py-0.5 rounded">● Publié</span>
                        @elseif ($status === 'draft')
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-700 bg-gray-100 dark:bg-white/5 dark:text-gray-300 px-2 py-0.5 rounded">● Brouillon</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-warning-700 bg-warning-50 dark:bg-warning-500/10 dark:text-warning-400 px-2 py-0.5 rounded">● {{ ucfirst($status) }}</span>
                        @endif
                    </div>
                    <select wire:model="status" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-[7px] bg-white dark:bg-gray-900 mb-3">
                        <option value="published">Publié</option>
                        <option value="draft">Brouillon</option>
                        <option value="scheduled">Programmé</option>
                        <option value="archived">Archivé</option>
                    </select>
                    <x-pko-product::switch-row label="Mis en avant" description="Apparaît en page d'accueil (bloc produits phares)." model="featured" />
                    @if ($status === 'scheduled')
                        <div class="mt-2">
                            <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Date de publication</label>
                            <input type="datetime-local" wire:model="publishAt" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900" />
                        </div>
                    @endif
            </x-pko-product::card>

            {{-- Organisation --}}
            <x-pko-product::card title="Organisation" icon="heroicon-o-tag">
                {{-- Catégories --}}
                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Catégories</label>
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach ($this->collectionOptions->whereIn('id', $collectionIds) as $coll)
                            <x-pko-product::chip color="primary" :onRemove="'removeCollection(' . $coll->id . ')'">
                                {{ $coll->translateAttribute('name') }}
                            </x-pko-product::chip>
                        @endforeach
                    </div>
                    <div class="relative">
                        <input
                            type="text"
                            wire:model.live.debounce.200ms="collectionSearch"
                            placeholder="Rechercher une catégorie…"
                            class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                        />
                        @if ($collectionSearch !== '' && $this->collectionSearchResults->isNotEmpty())
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded shadow-lg max-h-60 overflow-y-auto">
                                @foreach ($this->collectionSearchResults as $coll)
                                    <button
                                        type="button"
                                        wire:click="addCollection({{ $coll->id }})"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/5"
                                    >
                                        {{ $coll->translateAttribute('name') }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Marque --}}
                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Marque</label>
                    <select wire:model="brandId" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-[7px] bg-white dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($this->brandOptions as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tags --}}
                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Tags</label>
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach ($tagInputs as $tag)
                            <x-pko-product::chip color="gray" :onRemove="'removeTag(\'' . e($tag) . '\')'">
                                {{ $tag }}
                            </x-pko-product::chip>
                        @endforeach
                    </div>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            wire:model="newTag"
                            wire:keydown.enter.prevent="addTag"
                            placeholder="Nouveau tag…"
                            class="flex-1 text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                        />
                        <button type="button" wire:click="addTag" class="text-xs px-2 py-1 border border-gray-300 dark:border-white/10 rounded hover:bg-gray-50 dark:hover:bg-white/5">+</button>
                    </div>
                </div>
            </x-pko-product::card>

            {{-- Produits liés --}}
            <x-pko-product::card title="Produits liés" icon="heroicon-o-link">
                @forelse ($this->relatedProducts as $rel)
                    <div class="flex items-center gap-2 py-1">
                        <div class="w-10 h-10 bg-gray-100 dark:bg-white/5 rounded flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm truncate">{{ $rel->translateAttribute('name') }}</div>
                            <div class="text-[11.5px] font-mono text-gray-500">{{ $rel->variants->first()?->sku ?? '—' }}</div>
                        </div>
                        <button type="button" wire:click="removeRelatedProduct({{ $rel->id }})" class="text-gray-400 hover:text-danger-600 text-sm">&times;</button>
                    </div>
                @empty
                    <p class="text-xs text-gray-500">Aucun produit lié.</p>
                @endforelse

                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="relatedSearch"
                        placeholder="Rechercher un produit à lier…"
                        class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                    />
                    @if ($relatedSearch !== '' && $this->relatedSearchResults->isNotEmpty())
                        <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded shadow-lg max-h-60 overflow-y-auto">
                            @foreach ($this->relatedSearchResults as $item)
                                <button
                                    type="button"
                                    wire:click="addRelatedProduct({{ $item->id }})"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/5"
                                >
                                    {{ $item->translateAttribute('name') }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-pko-product::card>

            {{-- Historique --}}
            <x-pko-product::card title="Dernières modifications" icon="heroicon-o-clock">
                @forelse ($this->history as $entry)
                    <div class="flex items-start gap-2 py-1">
                        <span class="w-2 h-2 mt-1.5 rounded-full bg-primary-600 flex-shrink-0"></span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-900 dark:text-gray-200">{{ $entry->description ?: ucfirst($entry->event) }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $entry->causer?->name ?? 'Système' }} · {{ $entry->created_at?->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500">Aucun événement enregistré.</p>
                @endforelse
            </x-pko-product::card>
        </aside>

        {{-- ============================================================ --}}
        {{-- STICKY FOOTER (span full width sur lg, bottom de la page) --}}
        {{-- ============================================================ --}}
        <div class="lg:col-span-2 sticky bottom-0 z-20 -mx-4 md:-mx-6 px-4 md:px-6 py-3 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-white/10 shadow-[0_-2px_8px_rgba(0,0,0,0.04)] flex items-center justify-between flex-wrap gap-2">
            <div class="text-sm">
                @if ($isDirty)
                    <span class="text-warning-600">● Modifications non enregistrées</span>
                @else
                    <span class="text-success-600">✓ Toutes les modifications sont enregistrées</span>
                @endif
            </div>
            <div class="flex gap-2">
                @if ($this->storefrontUrl)
                    <a href="{{ $this->storefrontUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-3 py-[7px] text-sm border border-gray-300 dark:border-white/10 rounded hover:bg-gray-50 dark:hover:bg-white/5">
                        Aperçu
                    </a>
                @endif
                <button type="button" wire:click="saveAsDraft" wire:loading.attr="disabled" class="px-3 py-[7px] text-sm border border-gray-300 dark:border-white/10 rounded hover:bg-gray-50 dark:hover:bg-white/5">
                    Enregistrer comme brouillon
                </button>
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-[7px] text-sm font-medium bg-primary-600 hover:bg-primary-700 text-white rounded">
                    <span wire:loading.remove wire:target="save,saveAsDraft,saveAndPublish">Enregistrer</span>
                    <span wire:loading wire:target="save,saveAsDraft,saveAndPublish">Enregistrement…</span>
                </button>
            </div>
        </div>
    </form>
</x-filament-panels::page>

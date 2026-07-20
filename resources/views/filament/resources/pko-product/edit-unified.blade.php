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

            {{-- 2. Tarification --}}
            <x-pko-product::card title="Tarification" icon="heroicon-o-currency-euro">
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">Prix HT *</label>
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

                {{-- Marge (HT) — lecture seule, calculée depuis prix HT − coût --}}
                @if ($this->margin !== null)
                    <div class="flex items-center gap-2 text-[12.5px] text-gray-600 dark:text-gray-300">
                        <span class="font-medium">Marge (HT) :</span>
                        <span class="font-mono tabular-nums {{ $this->margin['amount'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ number_format($this->margin['amount'], 2, ',', ' ') }} €@if ($this->margin['percent'] !== null) <span class="text-gray-500">({{ number_format($this->margin['percent'], 1, ',', ' ') }} %)</span>@endif
                        </span>
                    </div>
                @endif
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

            {{-- 3. Médias + Vidéos côte à côte (desktop) / empilés (mobile) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <x-pko-product::card title="Médias" icon="heroicon-o-photo">
                    {{ $this->mediaForm }}
                </x-pko-product::card>

                <x-pko-product::card title="Vidéos" icon="heroicon-o-video-camera">
                    <div class="space-y-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Collez une URL YouTube, Vimeo, Dailymotion ou un lien <code>.mp4</code>. Le provider est détecté automatiquement. Glissez-déposez pour réordonner.
                        </p>

                        @if (count($this->videos) === 0)
                            <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 p-4 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                                Aucune vidéo pour l'instant.
                            </div>
                        @else
                            <div x-data x-sortable="reorderVideos" data-handle=".pko-video-handle" class="space-y-2">
                                @foreach ($this->videos as $index => $video)
                                    <x-product-videos::admin.video-row
                                        :index="$index"
                                        :video="$video"
                                        :providers="$this->videoProviders"
                                    />
                                @endforeach
                            </div>
                        @endif

                        <button
                            type="button"
                            wire:click="addVideoRow"
                            class="inline-flex items-center gap-1 rounded border border-dashed border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M10 3a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H4a1 1 0 1 1 0-2h5V4a1 1 0 0 1 1-1Z"/></svg>
                            Ajouter une vidéo
                        </button>
                    </div>
                </x-pko-product::card>
            </div>

            {{-- 4. Documents téléchargeables --}}
            <x-pko-product::card title="Documents téléchargeables" icon="heroicon-o-paper-clip">
                {{-- x-data écoute media-picked pour le statePath 'document-add-new' --}}
                <div
                    x-data="{
                        init() {
                            Livewire.on('media-picked', (payload) => {
                                const data = Array.isArray(payload) ? payload[0] : payload;
                                if (!data || data.statePath !== 'document-add-new') return;
                                if (!data.medias || !data.medias.length) return;
                                const items = data.medias.map(m => ({
                                    id: m.id,
                                    name: m.fileName ?? ''
                                }));
                                $wire.addDocumentsFromMedia(items);
                            });
                        }
                    }"
                    class="space-y-3"
                >
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Choisissez des fichiers depuis la médiathèque, puis assignez une catégorie à chaque document. Glissez-déposez pour réordonner.
                    </p>

                    @if (count($this->documents) === 0)
                        <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 p-4 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            Aucun document pour l'instant.
                        </div>
                    @else
                        <div x-data x-sortable="reorderDocuments" data-handle=".pko-doc-handle" class="space-y-2">
                            @foreach ($this->documents as $index => $document)
                                <x-product-documents::admin.document-row
                                    :index="$index"
                                    :document="$document"
                                    :categories="$this->documentCategories"
                                />
                            @endforeach
                        </div>
                    @endif

                    <button
                        type="button"
                        @click="Livewire.dispatch('open-media-picker-modal', { statePath: 'document-add-new', multiple: true })"
                        class="inline-flex items-center gap-1 rounded border border-dashed border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:text-gray-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M10 3a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H4a1 1 0 1 1 0-2h5V4a1 1 0 0 1 1-1Z"/></svg>
                        Ajouter depuis la médiathèque
                    </button>
                </div>
            </x-pko-product::card>

            {{-- 5. Description longue --}}
            <x-pko-product::card title="Description longue" icon="heroicon-o-document-text">
                {{ $this->descriptionForm }}
            </x-pko-product::card>

            {{-- 6. Caractéristiques techniques (CatalogFeatures) --}}
            <x-pko-product::card title="Caractéristiques techniques" icon="heroicon-o-list-bullet">
                <div class="space-y-3">
                    @forelse ($this->featureFamilies as $family)
                        <div class="grid grid-cols-[180px_1fr] gap-3 items-start">
                            <div class="pt-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $family->name }}
                                @if ($family->multi_value)
                                    <span class="ml-1 text-[10px] font-normal uppercase tracking-wide text-gray-400">multi</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @if ($family->multi_value)
                                    @foreach ($family->values as $value)
                                        <label class="cursor-pointer select-none">
                                            <input
                                                type="checkbox"
                                                wire:model="featureValues.{{ $family->id }}"
                                                value="{{ $value->id }}"
                                                class="peer sr-only"
                                            />
                                            <span class="inline-flex items-center rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs text-gray-600 transition hover:border-primary-400 peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-primary-400 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">
                                                {{ $value->name }}
                                            </span>
                                        </label>
                                    @endforeach
                                @else
                                    <label class="cursor-pointer select-none">
                                        <input type="radio" wire:model="featureValues.{{ $family->id }}" value="" class="peer sr-only" />
                                        <span class="inline-flex items-center rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs text-gray-500 transition hover:border-gray-400 peer-checked:border-gray-500 peer-checked:bg-gray-500 peer-checked:text-white dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
                                            —
                                        </span>
                                    </label>
                                    @foreach ($family->values as $value)
                                        <label class="cursor-pointer select-none">
                                            <input
                                                type="radio"
                                                wire:model="featureValues.{{ $family->id }}"
                                                value="{{ $value->id }}"
                                                class="peer sr-only"
                                            />
                                            <span class="inline-flex items-center rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs text-gray-600 transition hover:border-primary-400 peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-primary-400 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">
                                                {{ $value->name }}
                                            </span>
                                        </label>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500">Aucune famille de caractéristiques définie. Créez-en dans <strong>Catalogue → Caractéristiques</strong>.</p>
                    @endforelse
                </div>
            </x-pko-product::card>

            {{-- 7. Inventaire & expédition --}}
            <x-pko-product::card title="Inventaire & expédition" icon="heroicon-o-cube">
                {{-- Classe logistique — en haut de section --}}
                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('pko-shipping-common::admin.product.logistics_class') }}</label>
                    <select wire:model.live="logisticsClass" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900">
                        <option value="">— {{ __('pko-shipping-common::admin.product.logistics_class_none') }} —</option>
                        <option value="A">{{ __('pko-shipping-common::admin.product.logistics_class_a') }}</option>
                        <option value="B">{{ __('pko-shipping-common::admin.product.logistics_class_b') }}</option>
                        <option value="C">{{ __('pko-shipping-common::admin.product.logistics_class_c') }}</option>
                    </select>
                </div>

                <hr class="border-gray-200 dark:border-white/10" />

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

                <hr class="border-gray-200 dark:border-white/10" />

                <x-pko-product::switch-row
                    label="Frais de port offert"
                    description="Expédié directement par le fournisseur (dropshipping), port inclus dans le prix d'achat. Les lignes concernées sont exclues du calcul de livraison."
                    model="freeShipping"
                />

                {{-- Franco éligible --}}
                <x-pko-product::switch-row
                    :label="__('pko-shipping-common::admin.product.franco_eligible')"
                    :description="__('pko-shipping-common::admin.product.franco_eligible_help')"
                    model="francoEligible"
                />

                {{-- Prix transport dédié — visible uniquement si classe C --}}
                @if ($logisticsClass === 'C')
                    <div>
                        <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('pko-shipping-common::admin.product.transport_price') }}</label>
                        <div class="relative">
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.blur="transportPriceEuros"
                                class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 pr-8 text-right tabular-nums"
                                placeholder="0.00"
                            />
                            <span class="absolute right-3 top-[9px] text-xs text-gray-500">€</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ __('pko-shipping-common::admin.product.transport_price_help') }}</p>
                    </div>
                @endif

                {{-- Sur devis --}}
                <x-pko-product::switch-row
                    :label="__('pko-shipping-common::admin.product.quote_only')"
                    :description="__('pko-shipping-common::admin.product.quote_only_help')"
                    model="quoteOnly"
                />
            </x-pko-product::card>

            {{-- 8. Variantes --}}
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

            {{-- 9. Produits liés --}}
            <x-pko-product::card title="Produits liés" icon="heroicon-o-link">
                @php($thumbs = $this->relatedProductThumbnails)
                @if ($this->relatedProducts->isNotEmpty())
                    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-3">
                        @foreach ($this->relatedProducts as $rel)
                            <div class="group relative rounded-md border border-gray-200 dark:border-white/10 overflow-hidden">
                                <div class="relative aspect-square bg-gray-100 dark:bg-white/5">
                                    @if (isset($thumbs[$rel->id]))
                                        <img src="{{ $thumbs[$rel->id] }}" alt="" class="w-full h-full object-cover" loading="lazy" />
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                            </svg>
                                        </div>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="removeRelatedProduct({{ $rel->id }})"
                                        class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                                        title="Délier ce produit"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="p-1.5">
                                    <div class="text-[11px] font-medium text-gray-700 dark:text-gray-300 truncate leading-tight">{{ $rel->translateAttribute('name') }}</div>
                                    <div class="text-[10px] font-mono text-gray-500">{{ $rel->variants->first()?->sku ?? '—' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-500">Aucun produit lié pour l'instant.</p>
                @endif

                <div class="relative mt-2">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="relatedSearch"
                        wire:keydown.enter.prevent="addRelatedProductFromSearch"
                        placeholder="Rechercher par nom, SKU, EAN, tag…"
                        class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-3 py-[7px] bg-white dark:bg-gray-900 pr-8"
                    />
                    <span class="absolute right-3 top-[9px] text-gray-400 pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    </span>
                    @if ($relatedSearch !== '' && $this->relatedSearchResults->isNotEmpty())
                        <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded shadow-lg max-h-60 overflow-y-auto">
                            @foreach ($this->relatedSearchResults as $item)
                                <button
                                    type="button"
                                    wire:click="addRelatedProduct({{ $item->id }})"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/5 flex items-center gap-2"
                                >
                                    <span class="flex-1 truncate">{{ $item->translateAttribute('name') }}</span>
                                    <span class="font-mono text-xs text-gray-400 flex-shrink-0">{{ $item->variants->first()?->sku ?? '' }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-pko-product::card>

            {{-- 10. SEO --}}
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
                    @if ($status === 'published' && $productSlug !== '')
                        <a
                            href="{{ route('product.view', $productSlug) }}"
                            target="_blank"
                            rel="noopener"
                            class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                        >
                            {{ svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4') }}
                            Voir sur la boutique
                        </a>
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
                    <div
                        class="relative"
                        x-data="{
                            hi: -1,
                            items() { return this.$refs.results ? Array.from(this.$refs.results.querySelectorAll('[data-result]')) : []; },
                            move(dir) {
                                const items = this.items();
                                if (! items.length) { this.hi = -1; return; }
                                this.hi = (this.hi + dir + items.length) % items.length;
                                items[this.hi]?.scrollIntoView({ block: 'nearest' });
                            },
                            choose() {
                                const items = this.items();
                                if (! items.length) return;
                                (items[this.hi] ?? items[0])?.click();
                                this.hi = -1;
                            },
                        }"
                    >
                        <input
                            type="text"
                            wire:model.live.debounce.200ms="collectionSearch"
                            placeholder="Rechercher une catégorie… (↑ ↓ + Entrée)"
                            class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-1 bg-white dark:bg-gray-900"
                            autocomplete="off"
                            x-on:input="hi = -1"
                            x-on:keydown.arrow-down.prevent="move(1)"
                            x-on:keydown.arrow-up.prevent="move(-1)"
                            x-on:keydown.enter.prevent="choose()"
                            x-on:keydown.escape="hi = -1"
                        />
                        @if ($collectionSearch !== '' && $this->collectionSearchResults->isNotEmpty())
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded shadow-lg max-h-60 overflow-y-auto" x-ref="results">
                                @foreach ($this->collectionSearchResults as $coll)
                                    <button
                                        type="button"
                                        data-result
                                        wire:click="addCollection({{ $coll->id }})"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/5"
                                        x-bind:class="hi === {{ $loop->index }} ? 'bg-gray-100 dark:bg-white/10' : ''"
                                        x-on:mouseenter="hi = {{ $loop->index }}"
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

                {{-- Fournisseur --}}
                <div>
                    <label class="block text-[12.5px] font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('pko-shipping-common::admin.product.supplier') }}</label>
                    <select wire:model="supplierId" class="w-full text-sm border border-gray-300 dark:border-white/10 rounded px-2 py-[7px] bg-white dark:bg-gray-900">
                        <option value="">— {{ __('pko-shipping-common::admin.product.supplier_none') }} —</option>
                        @foreach ($this->supplierOptions as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
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
        {{-- FIXED FOOTER : offset left = largeur sidebar Filament (--sidebar-width
             défini dans :root par Filament). Plein large sur mobile (sidebar cachée). --}}
        {{-- ============================================================ --}}
        <style>
            .pko-edit-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 20;
            }
            @media (min-width: 1024px) {
                .pko-edit-footer {
                    left: var(--sidebar-width, 16rem);
                }
            }
        </style>
        <div class="pko-edit-footer px-4 md:px-6 py-3 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-white/10 shadow-[0_-2px_8px_rgba(0,0,0,0.04)] flex items-center justify-between flex-wrap gap-2">
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

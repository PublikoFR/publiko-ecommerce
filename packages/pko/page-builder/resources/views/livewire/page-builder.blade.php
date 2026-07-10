@php($full = $withMeta)
<div
    class="wk-editor {{ $full ? 'wk-editor--full' : '' }}"
    x-data="{ tab: '{{ $full ? 'page' : 'blocs' }}', previewOpen: false }"
    wire:key="wk-editor-{{ $recordId }}"
>
    {{-- ================================================================= --}}
    {{-- SPRITE ICÔNES                                                     --}}
    {{-- ================================================================= --}}
    <svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
        <symbol id="wk-i-plus" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="wk-i-trash" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V4.5h6V7M6.5 7l1 12.5h9l1-12.5"/></symbol>
        <symbol id="wk-i-copy" viewBox="0 0 24 24" fill="none"><rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></symbol>
        <symbol id="wk-i-grip" viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></symbol>
        <symbol id="wk-i-image" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="M4 17l5-5 4 4 3-3 4 4"/></symbol>
        <symbol id="wk-i-code" viewBox="0 0 24 24" fill="none"><path d="M9 8l-4 4 4 4M15 8l4 4-4 4"/></symbol>
        <symbol id="wk-i-text" viewBox="0 0 24 24" fill="none"><path d="M5 7h14M5 12h14M5 17h9"/></symbol>
        <symbol id="wk-i-quote" viewBox="0 0 24 24" fill="none"><path d="M7 7c-2 .8-3 2.4-3 4.8V17h5v-6H5.5c0-1.6.5-2.4 1.5-2.8ZM17 7c-2 .8-3 2.4-3 4.8V17h5v-6h-3.5c0-1.6.5-2.4 1.5-2.8Z"/></symbol>
        <symbol id="wk-i-button" viewBox="0 0 24 24" fill="none"><rect x="3" y="8" width="18" height="8" rx="4"/><path d="M8 12h5"/></symbol>
        <symbol id="wk-i-sep" viewBox="0 0 24 24" fill="none"><path d="M3 12h4M10 12h4M17 12h4"/></symbol>
        <symbol id="wk-i-chevron" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6"/></symbol>
        <symbol id="wk-i-search" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4-4"/></symbol>
        <symbol id="wk-i-external" viewBox="0 0 24 24" fill="none"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></symbol>
        <symbol id="wk-i-layers" viewBox="0 0 24 24" fill="none"><path d="M12 3l9 5-9 5-9-5 9-5ZM3 13l9 5 9-5"/></symbol>
        <symbol id="wk-i-file" viewBox="0 0 24 24" fill="none"><path d="M6 3h8l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v5h5"/></symbol>
        <symbol id="wk-i-move" viewBox="0 0 24 24" fill="none"><path d="M12 3v18M3 12h18M9 6l3-3 3 3M9 18l3 3 3-3M6 9l-3 3 3 3M18 9l3 3-3 3"/></symbol>
        <symbol id="wk-i-eye" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="wk-i-info" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></symbol>
        <symbol id="wk-i-warning" viewBox="0 0 24 24" fill="none"><path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4m0 3h.01"/></symbol>
        <symbol id="wk-i-danger" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></symbol>
        <symbol id="wk-i-title" viewBox="0 0 24 24" fill="none"><path d="M6 4v16M18 4v16M6 12h12M4 4h4M16 4h4M4 20h4M16 20h4"/></symbol>
        <symbol id="wk-i-video" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M10 9l5 3-5 3V9Z"/></symbol>
        <symbol id="wk-i-list" viewBox="0 0 24 24" fill="none"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></symbol>
        <symbol id="wk-i-accordion" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M17 7h.5M17 17h.5"/></symbol>
        <symbol id="wk-i-gallery" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></symbol>
    </defs></svg>

    {{-- ================================================================= --}}
    {{-- TOPBAR (mode plein)                                               --}}
    {{-- ================================================================= --}}
    @if ($full)
        <div class="wk-topbar">
            <div style="display:flex;align-items:center;gap:14px;min-width:0">
                <div class="wk-crumb">
                    <span>Contenus</span>
                    <svg class="wk-ico s16"><use href="#wk-i-chevron" style="transform:rotate(-90deg);transform-origin:center"/></svg>
                    <b>Modifier</b>
                </div>
                <span class="wk-vsep"></span>
                <span class="wk-doctitle" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $title !== '' ? $title : 'Sans titre' }}</span>
                @if ($status === 'published')
                    <span class="wk-badge wk-badge-pub"><span class="wk-dot"></span>Publié</span>
                @else
                    <span class="wk-badge wk-badge-draft">Brouillon</span>
                @endif
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <button type="button" class="wk-btn wk-btn-sec sm" x-on:click="previewOpen = true">
                    <svg class="wk-ico s16"><use href="#wk-i-eye"/></svg>Aperçu
                </button>
                <button type="button" class="wk-btn wk-btn-danger sm"
                    wire:click="deleteRecord"
                    wire:confirm="Supprimer définitivement ce contenu ?">
                    <svg class="wk-ico s16"><use href="#wk-i-trash"/></svg>Supprimer
                </button>
            </div>
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- BODY : canvas + panneau droit                                     --}}
    {{-- ================================================================= --}}
    <div class="wk-body">
        {{-- --------------------------- CANVAS --------------------------- --}}
        <div class="wk-canvas">
            <div class="wk-canvas-inner">
                @if ($full)
                    <div class="wk-canvas-hint">Canvas · /{{ $this->urlSegment ?: 'page' }}/{{ $slug ?: '…' }}</div>

                    {{-- Bloc H1 épinglé, non supprimable --}}
                    <div class="wk-blk wk-blk--h1" style="padding:16px 20px 18px;margin-bottom:16px">
                        <div class="wk-blk-kicker" style="color:var(--lime-800)">Titre H1 · affiché en haut de page</div>
                        <input
                            type="text"
                            class="wk-h1-input"
                            wire:model.blur="heading"
                            placeholder="Titre affiché aux visiteurs"
                        />
                        <p class="wk-hint" style="margin:2px 0 0">
                            Peut différer du nom en base @if($title !== '')« {{ $title }} » @endif(SEO). Non supprimable.
                        </p>
                    </div>
                @endif

                {{-- Sections réordonnables --}}
                <div x-pb-sortable="reorderSections" data-handle=".wk-handle"
                     style="display:flex;flex-direction:column;gap:16px">
                    @foreach ($sections as $sIndex => $section)
                        <div class="wk-section" wire:key="section-{{ $section['id'] }}" data-id="{{ $section['id'] }}">
                            <div class="wk-section-head">
                                <button type="button" class="wk-handle" title="Glisser pour réordonner">
                                    <svg class="wk-ico s16"><use href="#wk-i-grip"/></svg>
                                </button>
                                <span style="font:600 13px var(--font-sans);color:var(--text-secondary)">Section {{ $sIndex + 1 }}</span>

                                <div style="margin-left:auto;display:flex;align-items:center;gap:4px">
                                    @foreach ([1, 2, 3, 4, 5, 6] as $n)
                                        <button type="button"
                                            wire:click="setSectionLayout({{ $sIndex }}, '{{ $n }}col')"
                                            @class(['wk-layout-btn', 'on' => $section['layout'] === $n.'col'])
                                            title="{{ $n }} colonne{{ $n > 1 ? 's' : '' }}">{{ $n }}</button>
                                    @endforeach
                                    <button type="button" class="wk-btn-danger" style="border:none;background:none;cursor:pointer;padding:4px"
                                        wire:click="removeSection({{ $sIndex }})"
                                        wire:confirm="Supprimer cette section et ses blocs ?"
                                        title="Supprimer la section">
                                        <svg class="wk-ico s16"><use href="#wk-i-trash"/></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Style de section --}}
                            <details style="padding:8px 14px;border-bottom:1px solid var(--border-subtle)">
                                <summary style="cursor:pointer;font:500 12px var(--font-sans);color:var(--text-muted)">Style (marges, couleurs)</summary>
                                <div style="margin-top:12px;display:grid;grid-template-columns:repeat(2,1fr);gap:10px">
                                    @foreach (['t' => 'Padding ↑', 'r' => 'Padding →', 'b' => 'Padding ↓', 'l' => 'Padding ←'] as $side => $label)
                                        <label class="wk-fld" style="gap:3px">
                                            <span class="wk-hint">{{ $label }}</span>
                                            <input type="number" min="0" max="400" class="wk-inp" style="height:34px"
                                                value="{{ $section['padding'][$side] }}"
                                                wire:change="updateSectionPadding({{ $sIndex }}, '{{ $side }}', parseInt($event.target.value) || 0)" />
                                        </label>
                                    @endforeach
                                    <label class="wk-fld" style="gap:3px">
                                        <span class="wk-hint">Fond</span>
                                        <div style="display:flex;align-items:center;gap:6px">
                                            <input type="color" value="{{ $section['background_color'] ?? '#ffffff' }}"
                                                wire:change="updateSectionColor({{ $sIndex }}, 'background_color', $event.target.value)"
                                                style="height:34px;width:44px;border:1px solid var(--border-default);border-radius:6px;cursor:pointer" />
                                            <button type="button" class="wk-hint" style="border:none;background:none;cursor:pointer"
                                                wire:click="updateSectionColor({{ $sIndex }}, 'background_color', null)">reset</button>
                                        </div>
                                    </label>
                                    <label class="wk-fld" style="gap:3px">
                                        <span class="wk-hint">Texte</span>
                                        <div style="display:flex;align-items:center;gap:6px">
                                            <input type="color" value="{{ $section['text_color'] ?? '#000000' }}"
                                                wire:change="updateSectionColor({{ $sIndex }}, 'text_color', $event.target.value)"
                                                style="height:34px;width:44px;border:1px solid var(--border-default);border-radius:6px;cursor:pointer" />
                                            <button type="button" class="wk-hint" style="border:none;background:none;cursor:pointer"
                                                wire:click="updateSectionColor({{ $sIndex }}, 'text_color', null)">reset</button>
                                        </div>
                                    </label>
                                </div>
                            </details>

                            {{-- Colonnes + blocs --}}
                            <div @class([
                                'wk-cols',
                                'c1' => $section['layout'] === '1col',
                                'c2' => $section['layout'] === '2col',
                                'c3' => $section['layout'] === '3col',
                                'c4' => $section['layout'] === '4col',
                                'c5' => $section['layout'] === '5col',
                                'c6' => $section['layout'] === '6col',
                            ])>
                                @foreach ($section['columns'] as $cIndex => $column)
                                    <div class="wk-col" x-pb-drop data-drop-type="blocks"
                                        data-section-index="{{ $sIndex }}" data-column-index="{{ $cIndex }}">
                                        @foreach ($column['blocks'] as $block)
                                            <div class="wk-blk" style="padding:14px 16px" wire:key="block-{{ $block['id'] }}" data-id="{{ $block['id'] }}">
                                                <div class="wk-btool">
                                                    <button type="button" title="Dupliquer" wire:click="duplicateBlock('{{ $block['id'] }}')">
                                                        <svg class="wk-ico s16"><use href="#wk-i-copy"/></svg>
                                                    </button>
                                                    <button type="button" class="del" title="Supprimer"
                                                        wire:click="removeBlock('{{ $block['id'] }}')" wire:confirm="Supprimer ce bloc ?">
                                                        <svg class="wk-ico s16"><use href="#wk-i-trash"/></svg>
                                                    </button>
                                                </div>
                                                @include('page-builder::livewire._block-editor', ['block' => $block])
                                            </div>
                                        @endforeach

                                        @if (empty($column['blocks']))
                                            <div class="wk-dropzone wk-dropzone--sm">
                                                <svg class="wk-ico s16"><use href="#wk-i-plus"/></svg>Glissez un bloc ici
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Zone de drop pour une nouvelle section --}}
                <div class="wk-dropzone" style="margin-top:16px" x-pb-drop data-drop-type="sections">
                    <svg class="wk-ico s20" style="stroke:var(--lime-700)"><use href="#wk-i-plus"/></svg>
                    {{ count($sections) ? 'Déposez une section ici' : 'Glissez une section depuis l’onglet Blocs pour commencer' }}
                </div>
            </div>
        </div>

        {{-- ------------------------ PANNEAU DROIT ----------------------- --}}
        <div class="wk-panel">
            <div class="wk-tabs">
                @if ($full)
                    <button type="button" class="wk-tab" :class="{ 'on': tab==='page' }" x-on:click="tab='page'">
                        <svg class="wk-ico s16"><use href="#wk-i-file"/></svg>Page
                    </button>
                @endif
                <button type="button" class="wk-tab" :class="{ 'on': tab==='blocs' }" x-on:click="tab='blocs'">
                    <svg class="wk-ico s16"><use href="#wk-i-layers"/></svg>Blocs
                </button>
            </div>

            {{-- Onglet Page (métadonnées) --}}
            @if ($full)
                <div class="wk-panel-body" x-show="tab==='page'" x-cloak>
                    <div class="wk-fld">
                        <span class="wk-lbl">Type de contenu<span class="rq">*</span></span>
                        <select class="wk-sel" wire:model.live="postTypeId">
                            <option value="">—</option>
                            @foreach ($postTypeOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        <span class="wk-hint">/{{ $this->urlSegment ?: '?' }}/{slug}</span>
                        @error('postTypeId')<span class="wk-err">{{ $message }}</span>@enderror
                    </div>

                    <div class="wk-fld">
                        <span class="wk-lbl">Titre (nom en base)<span class="rq">*</span></span>
                        <input type="text" class="wk-inp" wire:model.blur="title" maxlength="200" />
                        @error('title')<span class="wk-err">{{ $message }}</span>@enderror
                    </div>

                    <div class="wk-fld">
                        <span class="wk-lbl">Slug<span class="rq">*</span></span>
                        <input type="text" class="wk-inp" wire:model.blur="slug" maxlength="200" />
                        @error('slug')<span class="wk-err">{{ $message }}</span>@enderror
                    </div>

                    <div class="wk-fld">
                        <span class="wk-lbl">Image de couverture</span>
                        <div class="wk-cover" wire:click="openCoverPicker">
                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="" />
                            @else
                                <svg class="wk-ico s20"><use href="#wk-i-image"/></svg>
                                <span>Choisir une image</span>
                            @endif
                        </div>
                        @if ($coverUrl)
                            <button type="button" class="wk-hint" style="align-self:flex-start;border:none;background:none;cursor:pointer"
                                wire:click="clearCover">Retirer la couverture</button>
                        @endif
                    </div>

                    <div class="wk-fld">
                        <span class="wk-lbl">Extrait</span>
                        <textarea class="wk-ta" wire:model.blur="excerpt" maxlength="500" placeholder="Court résumé…"></textarea>
                        @error('excerpt')<span class="wk-err">{{ $message }}</span>@enderror
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="wk-fld">
                            <span class="wk-lbl">Statut<span class="rq">*</span></span>
                            <select class="wk-sel" wire:model.live="status">
                                <option value="draft">Brouillon</option>
                                <option value="published">Publié</option>
                            </select>
                        </div>
                        <div class="wk-fld">
                            <span class="wk-lbl">Date</span>
                            <input type="datetime-local" class="wk-inp" wire:model.blur="publishedAt" />
                        </div>
                    </div>

                    <div class="wk-fld">
                        <span class="wk-lbl">SEO — Titre</span>
                        <input type="text" class="wk-inp" wire:model.blur="seoTitle" maxlength="255" placeholder="Titre SEO…" />
                        @error('seoTitle')<span class="wk-err">{{ $message }}</span>@enderror
                    </div>

                    <div class="wk-fld">
                        <span class="wk-lbl">SEO — Description</span>
                        <textarea class="wk-ta" wire:model.blur="seoDescription" maxlength="500" placeholder="Méta-description…"></textarea>
                        @error('seoDescription')<span class="wk-err">{{ $message }}</span>@enderror
                    </div>
                </div>
            @endif

            {{-- Onglet Blocs (palette) --}}
            <div class="wk-panel-body" x-show="tab==='blocs'" @if($full) x-cloak @endif>
                <div class="wk-search">
                    <svg class="wk-ico s16" style="stroke:var(--text-muted)"><use href="#wk-i-search"/></svg>
                    <input type="text" x-data="{ q: '' }" x-model="q"
                        x-on:input="$root.querySelectorAll('[data-palette-type]').forEach(t => t.style.display = t.dataset.label.toLowerCase().includes(q.toLowerCase()) ? '' : 'none')"
                        placeholder="Rechercher un bloc…" />
                </div>

                <div style="display:flex;align-items:center;gap:7px;font:500 12px var(--font-sans);color:var(--text-muted)">
                    <svg class="wk-ico s16" style="stroke:var(--text-muted)"><use href="#wk-i-move"/></svg>Glissez un élément vers le canvas
                </div>

                {{-- x-pb-palette DOIT être le parent DIRECT des [data-palette-type]
                     (SortableJS ne rend draggables que les enfants directs). --}}
                <div>
                    <p class="wk-over">Sections</p>
                    <div x-pb-palette style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                        <div class="wk-tile-sec" data-palette-type="section-1col" data-label="1 colonne section">
                            <div class="wk-colbar"><i></i></div><span style="font:600 11px var(--font-sans)">1 col.</span>
                        </div>
                        <div class="wk-tile-sec" data-palette-type="section-2col" data-label="2 colonnes section">
                            <div class="wk-colbar"><i></i><i></i></div><span style="font:600 11px var(--font-sans)">2 col.</span>
                        </div>
                        <div class="wk-tile-sec" data-palette-type="section-3col" data-label="3 colonnes section">
                            <div class="wk-colbar"><i></i><i></i><i></i></div><span style="font:600 11px var(--font-sans)">3 col.</span>
                        </div>
                        <div class="wk-tile-sec" data-palette-type="section-4col" data-label="4 colonnes section">
                            <div class="wk-colbar"><i></i><i></i><i></i><i></i></div><span style="font:600 11px var(--font-sans)">4 col.</span>
                        </div>
                        <div class="wk-tile-sec" data-palette-type="section-5col" data-label="5 colonnes section">
                            <div class="wk-colbar"><i></i><i></i><i></i><i></i><i></i></div><span style="font:600 11px var(--font-sans)">5 col.</span>
                        </div>
                        <div class="wk-tile-sec" data-palette-type="section-6col" data-label="6 colonnes section">
                            <div class="wk-colbar"><i></i><i></i><i></i><i></i><i></i><i></i></div><span style="font:600 11px var(--font-sans)">6 col.</span>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="wk-over">Blocs</p>
                    <div x-pb-palette style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                        <div class="wk-tile-blk" data-palette-type="text" data-label="Texte"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-text"/></svg></span>Texte</div>
                        <div class="wk-tile-blk" data-palette-type="image" data-label="Image"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-image"/></svg></span>Image</div>
                        <div class="wk-tile-blk" data-palette-type="code" data-label="Code"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-code"/></svg></span>Code</div>
                        <div class="wk-tile-blk" data-palette-type="quote" data-label="Citation"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-quote"/></svg></span>Citation</div>
                        <div class="wk-tile-blk" data-palette-type="button" data-label="Bouton"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-button"/></svg></span>Bouton</div>
                        <div class="wk-tile-blk" data-palette-type="separator" data-label="Séparateur"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-sep"/></svg></span>Séparateur</div>
                        <div class="wk-tile-blk" data-palette-type="callout-info" data-label="Info encart"><span class="ti" style="color:var(--info-600)"><svg class="wk-ico s16"><use href="#wk-i-info"/></svg></span>Info</div>
                        <div class="wk-tile-blk" data-palette-type="callout-warning" data-label="Attention encart"><span class="ti" style="color:var(--warning-600)"><svg class="wk-ico s16"><use href="#wk-i-warning"/></svg></span>Attention</div>
                        <div class="wk-tile-blk" data-palette-type="callout-danger" data-label="Danger encart"><span class="ti" style="color:var(--danger-600)"><svg class="wk-ico s16"><use href="#wk-i-danger"/></svg></span>Danger</div>
                        <div class="wk-tile-blk" data-palette-type="title" data-label="Titre"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-title"/></svg></span>Titre</div>
                        <div class="wk-tile-blk" data-palette-type="video" data-label="Vidéo"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-video"/></svg></span>Vidéo</div>
                        <div class="wk-tile-blk" data-palette-type="list" data-label="Liste"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-list"/></svg></span>Liste</div>
                        <div class="wk-tile-blk" data-palette-type="accordion" data-label="Accordéon FAQ"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-accordion"/></svg></span>Accordéon</div>
                        <div class="wk-tile-blk" data-palette-type="gallery" data-label="Galerie"><span class="ti"><svg class="wk-ico s16"><use href="#wk-i-gallery"/></svg></span>Galerie</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================= --}}
    {{-- BARRE D'ACTION STICKY                                             --}}
    {{-- ================================================================= --}}
    <div class="wk-actionbar">
        <div class="wk-savestate">
            <svg class="wk-ico s16" style="stroke:var(--text-muted)"><use href="#wk-i-layers"/></svg>
            {{ $this->blockCount }} bloc{{ $this->blockCount > 1 ? 's' : '' }}
            @if ($isDirty)
                · <span style="color:var(--danger-500)">modifications non enregistrées</span>
            @else
                · à jour
            @endif
        </div>
        <div style="display:flex;align-items:center;gap:10px">
            <button type="button" class="wk-btn wk-btn-sec" x-on:click="previewOpen = true">
                <svg class="wk-ico s20"><use href="#wk-i-eye"/></svg>Aperçu
            </button>
            <button type="button" class="wk-btn wk-btn-pri" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <svg class="wk-ico s20"><use href="#wk-i-file"/></svg>Enregistrer
            </button>
            @if ($full)
                <button type="button" class="wk-btn wk-btn-acc" wire:click="publish" wire:loading.attr="disabled" wire:target="publish">
                    Publier
                </button>
            @endif
        </div>
    </div>

    {{-- ================================================================= --}}
    {{-- APERÇU (slide-over)                                               --}}
    {{-- ================================================================= --}}
    <template x-teleport="body">
        <div x-show="previewOpen" x-transition.opacity x-on:keydown.escape.window="previewOpen = false"
            class="fixed inset-0 z-[60] bg-black/40" x-on:click.self="previewOpen = false" style="display:none">
            <aside x-show="previewOpen"
                x-transition:enter="transition transform ease-out duration-200"
                x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition transform ease-in duration-150"
                x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                class="fixed inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-white shadow-2xl">
                <header class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h2 class="text-base font-semibold">Aperçu</h2>
                    <button type="button" x-on:click="previewOpen = false" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto p-6">
                    <x-page-builder::render :content="$this->tree" :with-heading="true" />
                </div>
            </aside>
        </div>
    </template>

    <x-filament-actions::modals />
</div>

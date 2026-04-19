{{--
    Panneau détails / édition — colonne de droite persistante.
    Même markup page/modal. Les sections « usages » et « conversions » sont gated par flag.

    Variables attendues :
    - $focused : ?Media
    - $editName, $editTitle, $editAlt : string|null
    - $showUsagesAndConversions : bool
    - $usages : array|null
    - $conversions : array|null
--}}
<aside
    class="mlib-details"
    x-data="{
        state: 'saved',
        timer: null,
        MIN_MS: 900,
        markSaving() {
            this.state = 'saving';
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.state = 'saved'; }, this.MIN_MS);
        },
    }"
>
    @if (! $focused)
        <div class="mlib-details-empty">
            <x-filament::icon icon="heroicon-o-rectangle-stack" class="w-10 h-10 text-gray-300 dark:text-white/20" />
            <p class="text-sm text-gray-500 mt-3 text-center">Sélectionne un média<br>pour voir ses détails</p>
        </div>
    @else
        <header class="mlib-details-header">
            <h3 class="mlib-details-title">Détails</h3>
            <button type="button" wire:click="closeMedia" class="mlib-details-close" title="Fermer">
                <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
            </button>
        </header>

        <div class="mlib-details-body">
            @if (str_starts_with((string) $focused->mime_type, 'image/'))
                <div class="mlib-details-preview">
                    <img src="{{ $focused->getUrl() }}" alt="" />
                </div>
            @else
                <div class="mlib-details-preview is-file">
                    <x-filament::icon icon="heroicon-o-document" class="w-12 h-12 text-gray-400" />
                </div>
            @endif

            <dl class="mlib-details-meta">
                <div>
                    <dt>Fichier</dt>
                    <dd class="break-all">{{ $focused->file_name }}</dd>
                </div>
                <div>
                    <dt>Type</dt>
                    <dd>{{ $focused->mime_type }}</dd>
                </div>
                <div>
                    <dt>Taille</dt>
                    <dd>{{ number_format($focused->size / 1024, 1) }} Ko</dd>
                </div>
                <div>
                    <dt>Uploadé</dt>
                    <dd>{{ $focused->created_at?->format('d/m/Y') }}</dd>
                </div>
            </dl>

            <div class="flex flex-col gap-3">
                {{-- Autosave : chaque blur sync l'état puis le hook Livewire `updated()` persiste. --}}
                <div>
                    <label class="mlib-label-sm">Nom du fichier <span class="text-gray-400">(sans extension)</span></label>
                    <input type="text" wire:model.blur="editName" x-on:blur="markSaving()" class="mlib-input-sm font-mono" />
                    @error('editName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mlib-label-sm">Titre</label>
                    <input type="text" wire:model.blur="editTitle" x-on:blur="markSaving()" class="mlib-input-sm" />
                </div>
                <div>
                    <label class="mlib-label-sm">Alt <span class="text-gray-400">(accessibilité/SEO)</span></label>
                    <textarea wire:model.blur="editAlt" x-on:blur="markSaving()" rows="3" class="mlib-input-sm"></textarea>
                </div>
            </div>

            @if ($showUsagesAndConversions ?? false)
                <div class="mlib-details-section">
                    <h4>Utilisé par</h4>
                    @if (empty($usages))
                        <p class="mlib-details-empty-note">Aucune utilisation. Ce média peut être supprimé sans impact.</p>
                    @else
                        <ul class="mlib-usage-list">
                            @foreach ($usages as $usage)
                                <li>
                                    <div class="flex-1 min-w-0">
                                        <div class="mlib-usage-label">
                                            {{ $usage['label'] }}
                                            <span class="mlib-usage-group">· {{ $usage['mediagroup'] }}</span>
                                        </div>
                                        <div class="mlib-usage-title">{{ $usage['title'] }}</div>
                                    </div>
                                    @if ($usage['url'])
                                        <a href="{{ $usage['url'] }}" target="_blank" class="mlib-usage-link">Ouvrir ↗</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="mlib-details-section">
                    <h4>Tailles générées</h4>
                    @if (empty($conversions))
                        <p class="mlib-details-empty-note">Aucune conversion. Le cron d'auto-redimensionnement créera les variantes.</p>
                    @else
                        <ul class="mlib-conversion-list">
                            @foreach ($conversions as $conv)
                                <li>
                                    <span class="font-bold">{{ $conv['name'] }}</span>
                                    <a href="{{ $conv['url'] }}" target="_blank" class="mlib-usage-link">voir ↗</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>

        <footer class="mlib-details-footer">
            <span class="mlib-details-autosave" :class="state === 'saving' ? 'is-saving' : 'is-saved'">
                <span x-show="state === 'saved'" class="mlib-details-autosave-line">
                    <x-filament::icon icon="heroicon-s-check-circle" class="w-3.5 h-3.5" />
                    Auto-sauvegardé
                </span>
                <span x-show="state === 'saving'" x-cloak class="mlib-details-autosave-line">
                    <span class="mlib-details-autosave-dot"></span>
                    Enregistrement…
                </span>
            </span>
            <button
                type="button"
                x-on:click="if (confirm('Supprimer ce média ?')) { $wire.deleteSelectedMedia(); }"
                class="mlib-details-delete"
            >
                Supprimer
            </button>
        </footer>
    @endif
</aside>

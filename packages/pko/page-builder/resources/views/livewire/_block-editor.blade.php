@php
    $type = $block['type'] ?? null;
@endphp

@if ($type === 'text')
    <div class="wk-blk-kicker">Texte</div>
    <div style="margin-top:6px;font-size:14px;line-height:1.6;color:var(--text-secondary);max-height:110px;overflow:hidden">
        @if (trim(strip_tags($block['html'] ?? '')) === '')
            <em style="color:var(--text-muted)">Vide — cliquez sur Modifier</em>
        @else
            {!! \Illuminate\Support\Str::limit(strip_tags($block['html']), 220) !!}
        @endif
    </div>
    <button type="button" class="wk-btn wk-btn-sec sm" style="margin-top:10px"
        wire:click="mountAction('editText', { blockId: '{{ $block['id'] }}' })">Modifier le texte</button>

@elseif ($type === 'image')
    <div class="wk-blk-kicker">Image</div>
    @php
        $imgSrc = null;
        if (! empty($block['media_id'])) {
            $imgSrc = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($block['media_id'])?->getFullUrl();
        }
        $imgSrc = $imgSrc ?? ($block['url'] ?? null);
    @endphp
    <div style="margin-top:8px;display:flex;gap:10px;align-items:flex-start">
        @if ($imgSrc)
            <img src="{{ $imgSrc }}" style="height:64px;width:96px;flex:none;border-radius:var(--radius-sm);object-fit:cover;border:1px solid var(--border-subtle)" alt="" />
        @else
            <div style="height:64px;width:96px;flex:none;display:flex;align-items:center;justify-content:center;border:1.5px dashed var(--border-default);border-radius:var(--radius-sm);color:var(--text-muted)">
                <svg class="wk-ico s20"><use href="#wk-i-image"/></svg>
            </div>
        @endif
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:6px">
            <input type="text" class="wk-inp" style="height:34px" value="{{ $block['alt'] ?? '' }}"
                wire:change="updateImageAlt('{{ $block['id'] }}', $event.target.value)" placeholder="Texte alternatif (alt)" />
            <button type="button" class="wk-btn wk-btn-sec sm" style="align-self:flex-start"
                wire:click="openImagePicker('{{ $block['id'] }}')">Choisir une image</button>
        </div>
    </div>

@elseif ($type === 'code')
    <div class="wk-blk-kicker">Code</div>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px">
        <select class="wk-sel" style="height:34px;width:180px"
            wire:change="updateCodeBlock('{{ $block['id'] }}', 'language', $event.target.value)">
            @foreach (['plain' => 'Texte brut', 'php' => 'PHP', 'js' => 'JavaScript', 'ts' => 'TypeScript', 'html' => 'HTML', 'css' => 'CSS', 'bash' => 'Bash', 'json' => 'JSON', 'sql' => 'SQL', 'yaml' => 'YAML'] as $lang => $label)
                <option value="{{ $lang }}" @selected(($block['language'] ?? 'plain') === $lang)>{{ $label }}</option>
            @endforeach
        </select>
        <textarea rows="4" class="wk-ta" style="font-family:var(--font-mono);font-size:13px"
            wire:change="updateCodeBlock('{{ $block['id'] }}', 'content', $event.target.value)" placeholder="// code">{{ $block['content'] ?? '' }}</textarea>
    </div>

@elseif ($type === 'quote')
    <div class="wk-blk-kicker">Citation</div>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px">
        <textarea rows="3" class="wk-ta"
            wire:change="updateQuoteBlock('{{ $block['id'] }}', 'text', $event.target.value)" placeholder="Texte de la citation…">{{ $block['text'] ?? '' }}</textarea>
        <input type="text" class="wk-inp" style="height:34px" value="{{ $block['cite'] ?? '' }}"
            wire:change="updateQuoteBlock('{{ $block['id'] }}', 'cite', $event.target.value)" placeholder="Auteur / source (optionnel)" />
    </div>

@elseif ($type === 'button')
    <div class="wk-blk-kicker">Bouton</div>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px">
        <input type="text" class="wk-inp" style="height:34px" value="{{ $block['label'] ?? '' }}"
            wire:change="updateButtonBlock('{{ $block['id'] }}', 'label', $event.target.value)" placeholder="Libellé du bouton" maxlength="80" />
        <input type="text" class="wk-inp" style="height:34px" value="{{ $block['url'] ?? '' }}"
            wire:change="updateButtonBlock('{{ $block['id'] }}', 'url', $event.target.value)" placeholder="https://… ou /page-interne" />
        <select class="wk-sel" style="height:34px;width:180px"
            wire:change="updateButtonBlock('{{ $block['id'] }}', 'variant', $event.target.value)">
            @foreach (['primary' => 'Principal (forest)', 'accent' => 'Accent (lime)', 'secondary' => 'Secondaire (contour)'] as $v => $label)
                <option value="{{ $v }}" @selected(($block['variant'] ?? 'primary') === $v)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

@elseif ($type === 'separator')
    <div class="wk-blk-kicker">Séparateur</div>
    <div style="margin-top:8px">
        <select class="wk-sel" style="height:34px;width:200px"
            wire:change="updateSeparatorBlock('{{ $block['id'] }}', $event.target.value)">
            @foreach (['line' => 'Ligne', 'space-sm' => 'Espace S', 'space-md' => 'Espace M', 'space-lg' => 'Espace L', 'space-xl' => 'Espace XL'] as $v => $label)
                <option value="{{ $v }}" @selected(($block['variant'] ?? 'line') === $v || (($block['variant'] ?? '') === 'space' && $v === 'space-md'))>{{ $label }}</option>
            @endforeach
        </select>
    </div>

@elseif ($type === 'callout')
    @php($cv = $block['variant'] ?? 'info')
    <div class="wk-blk-kicker">Encart · {{ ['info' => 'Info', 'warning' => 'Attention', 'danger' => 'Danger'][$cv] ?? 'Info' }}</div>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px">
        <select class="wk-sel" style="height:34px;width:180px"
            wire:change="updateCalloutBlock('{{ $block['id'] }}', 'variant', $event.target.value)">
            @foreach (['info' => 'Info (bleu)', 'warning' => 'Attention (orange)', 'danger' => 'Danger (rouge)'] as $v => $label)
                <option value="{{ $v }}" @selected($cv === $v)>{{ $label }}</option>
            @endforeach
        </select>
        <textarea rows="2" class="wk-ta"
            wire:change="updateCalloutBlock('{{ $block['id'] }}', 'text', $event.target.value)" placeholder="Message de l'encart…">{{ $block['text'] ?? '' }}</textarea>
    </div>

@elseif ($type === 'title')
    <div class="wk-blk-kicker">Titre</div>
    <div style="margin-top:8px;display:flex;gap:8px">
        <select class="wk-sel" style="height:38px;width:90px"
            wire:change="updateTitleBlock('{{ $block['id'] }}', 'level', $event.target.value)">
            @foreach (['h2' => 'H2', 'h3' => 'H3'] as $v => $label)
                <option value="{{ $v }}" @selected(($block['level'] ?? 'h2') === $v)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="text" class="wk-inp" style="flex:1" value="{{ $block['text'] ?? '' }}"
            wire:change="updateTitleBlock('{{ $block['id'] }}', 'text', $event.target.value)" placeholder="Texte du titre" maxlength="200" />
    </div>

@elseif ($type === 'video')
    <div class="wk-blk-kicker">Vidéo</div>
    <div style="margin-top:8px">
        <input type="text" class="wk-inp" style="height:34px" value="{{ $block['url'] ?? '' }}"
            wire:change="updateVideoBlock('{{ $block['id'] }}', $event.target.value)" placeholder="URL YouTube, Vimeo, Dailymotion ou .mp4" />
        <p class="wk-hint" style="margin-top:6px">La vidéo est intégrée automatiquement à partir de l'URL.</p>
    </div>

@elseif ($type === 'list')
    @php($items = is_array($block['items'] ?? null) ? $block['items'] : [])
    <div class="wk-blk-kicker">Liste</div>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px">
        <select class="wk-sel" style="height:34px;width:180px"
            wire:change="updateListStyle('{{ $block['id'] }}', $event.target.value)">
            @foreach (['bullet' => 'Puces', 'check' => 'Coches ✓'] as $v => $label)
                <option value="{{ $v }}" @selected(($block['style'] ?? 'bullet') === $v)>{{ $label }}</option>
            @endforeach
        </select>
        <textarea rows="4" class="wk-ta"
            wire:change="updateListItems('{{ $block['id'] }}', $event.target.value)" placeholder="Un élément par ligne">{{ implode("\n", $items) }}</textarea>
    </div>

@elseif ($type === 'accordion')
    @php($items = is_array($block['items'] ?? null) ? $block['items'] : [])
    <div class="wk-blk-kicker">Accordéon / FAQ</div>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:10px">
        @foreach ($items as $i => $item)
            <div wire:key="acc-{{ $block['id'] }}-{{ $i }}" style="border:1px solid var(--border-subtle);border-radius:var(--radius-md);padding:10px;display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;gap:6px;align-items:center">
                    <input type="text" class="wk-inp" style="height:32px;flex:1" value="{{ $item['q'] ?? '' }}"
                        wire:change="updateAccordionItem('{{ $block['id'] }}', {{ $i }}, 'q', $event.target.value)" placeholder="Question" />
                    <button type="button" class="wk-btn-danger" style="border:none;background:none;cursor:pointer;padding:4px"
                        wire:click="removeAccordionItem('{{ $block['id'] }}', {{ $i }})" title="Retirer">
                        <svg class="wk-ico s16"><use href="#wk-i-trash"/></svg>
                    </button>
                </div>
                <textarea rows="2" class="wk-ta"
                    wire:change="updateAccordionItem('{{ $block['id'] }}', {{ $i }}, 'a', $event.target.value)" placeholder="Réponse">{{ $item['a'] ?? '' }}</textarea>
            </div>
        @endforeach
        <button type="button" class="wk-btn wk-btn-sec sm" style="align-self:flex-start"
            wire:click="addAccordionItem('{{ $block['id'] }}')">+ Ajouter une question</button>
    </div>

@elseif ($type === 'gallery')
    @php($gids = is_array($block['media_ids'] ?? null) ? $block['media_ids'] : [])
    <div class="wk-blk-kicker">Galerie</div>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px">
        @if (! empty($gids))
            @php($gmedias = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->whereIn('id', $gids)->get()->keyBy('id'))
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px">
                @foreach ($gids as $gid)
                    @php($gm = $gmedias->get($gid))
                    @if ($gm)
                        <div wire:key="gal-{{ $block['id'] }}-{{ $gid }}" style="position:relative">
                            <img src="{{ $gm->getFullUrl() }}" style="width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border-subtle)" alt="" />
                            <button type="button" wire:click="removeGalleryImage('{{ $block['id'] }}', {{ $gid }})"
                                style="position:absolute;top:2px;right:2px;background:#fff;border:1px solid var(--border-subtle);border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--danger-500)" title="Retirer">×</button>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
        <div style="display:flex;gap:8px;align-items:center">
            <button type="button" class="wk-btn wk-btn-sec sm" wire:click="openGalleryPicker('{{ $block['id'] }}')">Ajouter des images</button>
            <select class="wk-sel" style="height:34px;width:120px"
                wire:change="updateGalleryColumns('{{ $block['id'] }}', parseInt($event.target.value))">
                @foreach ([2 => '2 col.', 3 => '3 col.', 4 => '4 col.'] as $v => $label)
                    <option value="{{ $v }}" @selected((int) ($block['columns'] ?? 3) === $v)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
@endif

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
        <select class="wk-sel" style="height:34px;width:180px"
            wire:change="updateSeparatorBlock('{{ $block['id'] }}', $event.target.value)">
            @foreach (['line' => 'Ligne', 'space' => 'Espace'] as $v => $label)
                <option value="{{ $v }}" @selected(($block['variant'] ?? 'line') === $v)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
@endif

{{--
    Cellule éditable au double-clic pour la table staging.

    Props :
      $value     — valeur actuelle (scalar ou stringifiable)
      $recordId  — int, identifiant du StagingRecord
      $key       — string, clé dans data[]

    Le composant Alpine appelle $wire.updateCellValue() sur le RelationManager
    (qui est le composant Livewire parent), ce qui persiste la valeur puis
    re-rend la table avec la nouvelle valeur.
--}}
@php
    $display = match (true) {
        is_array($value)  => json_encode($value, JSON_UNESCAPED_UNICODE),
        is_null($value)   => '',
        default           => (string) $value,
    };
@endphp
<div
    x-data="{
        editing: false,
        draft: '',
        display: @js($display),
        start() {
            this.draft = this.display;
            this.editing = true;
            this.$nextTick(() => {
                const inp = this.$refs.inp;
                if (inp) { inp.focus(); inp.select(); }
            });
        },
        commit() {
            if (!this.editing) return;
            this.editing = false;
            this.display = this.draft;
            $wire.updateCellValue(@js($recordId), @js($key), this.draft);
        },
        cancel() { this.editing = false; }
    }"
    @dblclick.stop="start()"
    class="min-w-[80px] cursor-pointer"
    title="{{ $display }}"
>
    <span
        x-show="!editing"
        x-text="display || '—'"
        class="block max-w-[180px] truncate text-sm"
    ></span>
    <input
        x-show="editing"
        x-ref="inp"
        x-model="draft"
        @blur="commit()"
        @keydown.enter.prevent="commit()"
        @keydown.escape.prevent="cancel()"
        @click.stop
        type="text"
        class="w-full rounded border border-primary-400 bg-white px-1 py-0.5 text-sm dark:border-primary-500 dark:bg-gray-800"
    />
</div>

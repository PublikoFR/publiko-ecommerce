{{--
    Contenu du modal « Configurer » — éditeur de pipeline d'actions.

    Reproduction fidèle du modal du module PrestaShop : panneau gauche (champs
    source + flux d'actions avec connecteurs colorés SI/ALORS/SINON/PUIS),
    panneau droit (palette d'actions catégorisée). Le markup porte les MÊMES ids
    que ceux attendus par le moteur lifté (resources/js/pipeline-editor.js).

    `wire:ignore` protège le DOM construit par le moteur JS du morphing Livewire.
    Le montage et la synchronisation passent par Alpine (x-init), seul mécanisme
    fiable dans un contenu de modal chargé par Livewire.

    Variables : $seed (array {sheet, col, default, actions[]}), $label (string).
--}}
<div
    wire:ignore
    class="pko-pipeline-editor"
    x-data="{
        seed: @js($seed),
        label: @js($label),
        booted: false,
        boot() {
            if (this.booted) return;
            this.booted = true;
            const root = this.$el;
            const sync = (state) => {
                const hidden = document.querySelector('input[data-pko-pipeline-json]');
                if (!hidden) return;
                hidden.value = JSON.stringify(state);
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            };
            const tryMount = (n) => {
                if (window.PkoPipelineEditor && typeof window.PkoPipelineEditor.mount === 'function') {
                    window.PkoPipelineEditor.mount({ root, seed: this.seed, label: this.label, onChange: sync });
                } else if (n < 50) {
                    setTimeout(() => tryMount(n + 1), 60);
                }
            };
            tryMount(0);
        }
    }"
    x-init="boot()"
>
    {{-- ── Panneau gauche : champs source + flux d'actions ─────────────── --}}
    <div class="pko-pe-left">
        <div class="modal-source-fields">
            <div class="field-group">
                <label>Feuille</label>
                <input type="text" class="form-control input-sm" id="modal-sheet-input" placeholder="Principale si vide">
            </div>
            <div class="field-group field-col">
                <label>Col.</label>
                <input type="text" class="form-control input-sm" id="modal-col-input" placeholder="M, AA…">
            </div>
            <div class="field-group">
                <label>Par défaut</label>
                <input type="text" class="form-control input-sm" id="modal-default-input" placeholder="—">
            </div>
        </div>

        <div id="flow-area">
            <label><i class="icon-list-ol"></i> Pipeline d'actions</label>
            <div id="pipeline-empty">
                <i class="icon-arrow-right"></i>
                Cliquez sur une action à droite pour commencer
            </div>
            <div class="pipeline-flow" id="pipeline-list"></div>

            <button type="button" class="btn btn-danger btn-xs pko-pe-clear" id="clear-actions-btn">
                <i class="icon-trash"></i> Tout effacer
            </button>
        </div>
    </div>

    {{-- ── Panneau droit : palette d'actions ───────────────────────────── --}}
    <div class="pko-pe-right">
        <span class="pko-pe-panel-title"><i class="icon-th"></i> Ajouter une action</span>
        <div id="action-grid"></div>
    </div>

    {{-- ── Sous-modal clé/valeur (table de correspondance) ─────────────── --}}
    <div id="keyvalue-modal">
        <div class="pko-kv-dialog">
            <div class="pko-kv-head">
                <span>Table de correspondance</span>
                <button type="button" class="close" onclick="document.getElementById('keyvalue-modal').style.display='none'">&times;</button>
            </div>
            <div class="pko-kv-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Valeur source</th>
                            <th>Valeur cible</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="keyvalue-tbody"></tbody>
                </table>
                <button type="button" class="btn btn-default btn-xs" id="add-keyvalue-btn">
                    <i class="icon-plus"></i> Ajouter une ligne
                </button>
            </div>
            <div class="pko-kv-foot">
                <button type="button" class="btn btn-default" onclick="document.getElementById('keyvalue-modal').style.display='none'">Annuler</button>
                <button type="button" class="btn btn-primary" id="save-keyvalue-btn">Appliquer</button>
            </div>
        </div>
    </div>
</div>

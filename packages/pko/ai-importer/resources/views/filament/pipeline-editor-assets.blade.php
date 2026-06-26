{{--
    Assets de l'éditeur de pipeline, injectés UNE fois au rendu des pages
    Create/Edit d'une configuration d'import (via un render hook Filament).

    Pourquoi au niveau page et non dans le modal : le contenu d'un modal Filament
    est chargé par Livewire, où les <script> inline ne s'exécutent pas de façon
    fiable. En l'injectant dans le HTML initial de la page, le moteur
    (window.PkoPipelineEditor) et les globals (actionTypes…) existent avant toute
    ouverture de modal ; la vue du modal n'a plus qu'à appeler mount() via Alpine.
--}}
@php
    use Pko\AiImporter\Support\PipelineEditorManifest;
    use Pko\AiImporter\Support\ProductFieldCatalog;

    // $cssPath / $jsPath sont injectés par le render hook (chemins absolus).

    // Colonnes cibles proposées dans les sélecteurs de colonnes du moteur
    // (équivalent du `prestashopColumns` du module d'origine).
    $columns = ProductFieldCatalog::flat();

    // Configurations LLM disponibles, au format attendu par le moteur.
    $llmConfigs = [];
    if (class_exists(\Pko\AiCore\Models\LlmConfig::class)) {
        $llmConfigs = \Pko\AiCore\Models\LlmConfig::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id_llm_config' => $c->id,
                'name' => $c->name,
                'provider' => $c->provider ?? '',
                'model' => $c->model ?? '',
            ])
            ->all();
    }

    // Quelques libellés FR pour les rares clés sans fallback dans le moteur.
    $translations = [
        'configEditor' => [
            'addCondition' => 'Ajouter une condition',
            'editValues' => 'Modifier (%s valeurs)',
            'fixedValueOrActions' => 'Valeur fixe (ou vide pour utiliser les actions)',
            'selectLlmConfig' => '— Configuration LLM —',
            'jsonKeyHeader' => 'Clé JSON',
            'excelColumnHeader' => 'Colonne Excel',
            'dragToReorder' => 'Glisser pour réordonner',
        ],
    ];
@endphp

<style>{!! file_get_contents($cssPath) !!}</style>
<script>
    window.actionTypes = @js(PipelineEditorManifest::actionTypes());
    window.actionGroups = @js(PipelineEditorManifest::groups());
    window.conditionOperators = @js(PipelineEditorManifest::conditionOperators());
    window.prestashopColumns = @js($columns);
    window.llmConfigs = @js($llmConfigs);
    window.pkoaiTranslations = @js($translations);
</script>
<script>{!! file_get_contents($jsPath) !!}</script>

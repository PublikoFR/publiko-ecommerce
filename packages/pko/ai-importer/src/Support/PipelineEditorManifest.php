<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use Pko\AiImporter\Actions\ActionRegistry;
use Pko\AiImporter\Actions\Types\ChangeCaseAction;
use Pko\AiImporter\Actions\Types\MathAction;
use Pko\AiImporter\Services\ActionPipeline;

/**
 * Manifeste de la palette d'actions pour l'éditeur de pipeline (vue custom
 * reproduisant le modal « Pipeline d'actions » du module PrestaShop).
 *
 * Reproduction fidèle de `AdminPublikoConfigController::getActionTypes()` du
 * module d'origine : même découpage en groupes, mêmes icônes, mêmes schémas de
 * paramètres. Les libellés sont en français (UI).
 *
 * **Round-trip** : les types émis ici (y compris les types legacy `multiply`,
 * `uppercase`… séparés) sont tous exécutables côté Lunar — {@see ActionRegistry}
 * enregistre les alias et {@see MathAction::fromArray()} /
 * {@see ChangeCaseAction::fromArray()} les
 * normalisent. L'éditeur sérialise donc directement vers le `config_data`
 * canonique consommé par {@see ActionPipeline}, sans
 * couche de traduction.
 *
 * Le moteur JS (`resources/js/pipeline-editor.js`) interprète chaque `type` de
 * paramètre : number, text, checkbox, select, textarea, keyvalue,
 * columns_select, columns_mapping, source_columns_mapping, sources,
 * sourcesobject, llm_select.
 */
final class PipelineEditorManifest
{
    /**
     * Groupes (ordre + libellé) — l'éditeur affiche les sections dans cet ordre.
     *
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return [
            'logic' => 'Logique',
            'math' => 'Mathématique',
            'text' => 'Texte',
            'search_replace' => 'Rechercher / Remplacer',
            'combine' => 'Combiner colonnes',
            'lookup' => 'Correspondance',
            'date_validation' => 'Dates / Validation',
            'ai' => 'Intelligence artificielle',
            'aggregation' => 'Agrégation multi-lignes',
        ];
    }

    /**
     * Opérateurs de condition (SI) — `valeur => libellé`.
     *
     * @return array<string, string>
     */
    public static function conditionOperators(): array
    {
        return [
            '=' => '= (égal)',
            '!=' => '≠ (différent)',
            '>' => '> (supérieur)',
            '<' => '< (inférieur)',
            '>=' => '≥ (sup. ou égal)',
            '<=' => '≤ (inf. ou égal)',
            'contains' => 'contient',
            'not_contains' => 'ne contient pas',
            'empty' => 'est vide',
            'not_empty' => 'non vide',
        ];
    }

    /**
     * Catalogue des types d'action (carte de la sidebar + schéma de params).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function actionTypes(): array
    {
        return [
            // ── Logique ─────────────────────────────────────────────────────
            ['type' => 'condition', 'label' => 'Condition (SI / ALORS / SINON)', 'group' => 'logic', 'icon' => 'icon-code-fork',
                'description' => 'Branche conditionnelle : SI les règles passent, exécute ALORS, sinon exécute SINON.',
                'params' => []],

            // ── Mathématique ────────────────────────────────────────────────
            ['type' => 'multiply', 'label' => 'Multiplier', 'group' => 'math', 'icon' => 'icon-asterisk',
                'params' => ['value' => ['type' => 'number', 'label' => 'Multiplicateur', 'step' => '0.01']]],
            ['type' => 'divide', 'label' => 'Diviser', 'group' => 'math', 'icon' => 'icon-columns',
                'params' => ['value' => ['type' => 'number', 'label' => 'Diviseur', 'step' => '0.01']]],
            ['type' => 'add', 'label' => 'Additionner', 'group' => 'math', 'icon' => 'icon-plus',
                'params' => ['value' => ['type' => 'number', 'label' => 'Valeur à ajouter', 'step' => '0.01']]],
            ['type' => 'subtract', 'label' => 'Soustraire', 'group' => 'math', 'icon' => 'icon-minus',
                'params' => ['value' => ['type' => 'number', 'label' => 'Valeur à soustraire', 'step' => '0.01']]],
            ['type' => 'round', 'label' => 'Arrondir', 'group' => 'math', 'icon' => 'icon-dot-circle-o',
                'params' => ['decimals' => ['type' => 'number', 'label' => 'Décimales', 'step' => '1', 'default' => 2]]],

            // ── Texte ───────────────────────────────────────────────────────
            ['type' => 'uppercase', 'label' => 'Majuscules', 'group' => 'text', 'icon' => 'icon-font', 'params' => []],
            ['type' => 'lowercase', 'label' => 'Minuscules', 'group' => 'text', 'icon' => 'icon-text-height', 'params' => []],
            ['type' => 'capitalize', 'label' => 'Première lettre majuscule', 'group' => 'text', 'icon' => 'icon-italic', 'params' => []],
            ['type' => 'trim', 'label' => 'Supprimer espaces', 'group' => 'text', 'icon' => 'icon-eraser', 'params' => []],
            ['type' => 'slugify', 'label' => 'Slug (URL)', 'group' => 'text', 'icon' => 'icon-link',
                'params' => ['lowercase' => ['type' => 'checkbox', 'label' => 'Minuscules', 'default' => true]]],
            ['type' => 'truncate', 'label' => 'Tronquer', 'group' => 'text', 'icon' => 'icon-scissors',
                'params' => [
                    'length' => ['type' => 'number', 'label' => 'Longueur max', 'step' => '1', 'default' => 100],
                    'suffix' => ['type' => 'text', 'label' => 'Suffixe', 'default' => '...'],
                ]],

            // ── Rechercher / Remplacer ──────────────────────────────────────
            ['type' => 'replace', 'label' => 'Remplacer', 'group' => 'search_replace', 'icon' => 'icon-exchange',
                'params' => [
                    'search' => ['type' => 'text', 'label' => 'Rechercher'],
                    'replace' => ['type' => 'text', 'label' => 'Remplacer par'],
                ]],
            ['type' => 'regex_replace', 'label' => 'Remplacer (regex)', 'group' => 'search_replace', 'icon' => 'icon-magic',
                'params' => [
                    'pattern' => ['type' => 'text', 'label' => 'Motif (expression régulière)'],
                    'replace' => ['type' => 'text', 'label' => 'Remplacer par'],
                ]],

            // ── Combiner colonnes ───────────────────────────────────────────
            ['type' => 'concat', 'label' => 'Concaténer', 'group' => 'combine', 'icon' => 'icon-compress',
                'params' => [
                    'separator' => ['type' => 'text', 'label' => 'Séparateur', 'default' => ','],
                    'sources' => ['type' => 'sources', 'label' => 'Sources'],
                ]],
            ['type' => 'template', 'label' => 'Template', 'group' => 'combine', 'icon' => 'icon-code',
                'params' => [
                    'template' => ['type' => 'text', 'label' => 'Template (ex : {NOM} - {MARQUE})'],
                    'sources' => ['type' => 'sourcesobject', 'label' => 'Variables'],
                ]],
            ['type' => 'prefix', 'label' => 'Préfixe', 'group' => 'combine', 'icon' => 'icon-indent',
                'description' => 'Ajoute un préfixe à la valeur (texte fixe ou depuis une autre colonne).',
                'params' => [
                    'text' => ['type' => 'text', 'label' => 'Préfixe fixe', 'help' => 'Texte ajouté avant la valeur (optionnel si une source est définie)'],
                    'source' => ['type' => 'text', 'label' => 'Ou colonne source (Feuille:Col)', 'help' => 'Ex : B01_COMMERCE:D pour prendre le préfixe dans une autre colonne'],
                    'source_length' => ['type' => 'number', 'label' => 'Nombre de caractères de la source', 'help' => 'Limite le préfixe aux N premiers caractères (vide = tout)', 'default' => ''],
                    'separator' => ['type' => 'text', 'label' => 'Séparateur', 'help' => 'Caractère entre le préfixe et la valeur (ex : - ou _)', 'default' => ''],
                    'uppercase' => ['type' => 'select', 'label' => 'Casse du préfixe', 'options' => ['' => 'Ne pas modifier', 'upper' => 'MAJUSCULES', 'lower' => 'minuscules'], 'default' => ''],
                ]],
            ['type' => 'copy', 'label' => 'Copier colonne', 'group' => 'combine', 'icon' => 'icon-copy',
                'params' => ['col' => ['type' => 'text', 'label' => 'Nom de la colonne cible']]],

            // ── Correspondance ──────────────────────────────────────────────
            ['type' => 'map', 'label' => 'Table de valeurs', 'group' => 'lookup', 'icon' => 'icon-random',
                'params' => ['values' => ['type' => 'keyvalue', 'label' => 'Correspondances']]],
            ['type' => 'category_map', 'label' => 'Mapping catégories', 'group' => 'lookup', 'icon' => 'icon-sitemap',
                'params' => [
                    'values' => ['type' => 'keyvalue', 'label' => 'Correspondances'],
                    'default_category' => ['type' => 'text', 'label' => 'Catégorie par défaut'],
                ]],
            ['type' => 'conditional', 'label' => 'Conditionnel', 'group' => 'lookup', 'icon' => 'icon-question-circle',
                'params' => [
                    'condition' => ['type' => 'text', 'label' => 'Condition (ex : > 0, = test)'],
                    'if_true' => ['type' => 'text', 'label' => 'Si vrai'],
                    'if_false' => ['type' => 'text', 'label' => 'Si faux'],
                ]],

            // ── Dates / Validation ──────────────────────────────────────────
            ['type' => 'date_format', 'label' => 'Format de date', 'group' => 'date_validation', 'icon' => 'icon-calendar',
                'params' => [
                    'from' => ['type' => 'text', 'label' => 'Format source (ex : d/m/Y)'],
                    'to' => ['type' => 'text', 'label' => 'Format cible', 'default' => 'Y-m-d'],
                ]],
            ['type' => 'validate_ean13', 'label' => 'Valider EAN13', 'group' => 'date_validation', 'icon' => 'icon-barcode', 'params' => []],

            // ── Intelligence artificielle ───────────────────────────────────
            ['type' => 'llm_transform', 'label' => 'Prompt IA', 'group' => 'ai', 'icon' => 'icon-bolt',
                'params' => [
                    'llm_config_id' => ['type' => 'llm_select', 'label' => 'Configuration LLM'],
                    'prompt' => ['type' => 'textarea', 'label' => 'Prompt système', 'rows' => 4, 'placeholder' => 'Tu es un assistant qui génère des descriptions produit SEO...'],
                    'input_columns' => ['type' => 'columns_select', 'label' => 'Colonnes à inclure (après actions appliquées)', 'multiple' => true],
                    'additional_context' => ['type' => 'textarea', 'label' => 'Contexte additionnel', 'rows' => 2, 'placeholder' => 'Catalogue de produits électroménager...'],
                    'output_format' => ['type' => 'select', 'label' => 'Format de sortie', 'options' => ['string' => 'Texte brut', 'json' => 'JSON structuré'], 'default' => 'string'],
                    'output_json_key' => ['type' => 'text', 'label' => 'Clé JSON de sortie', 'placeholder' => 'Ex : description', 'help' => 'Clé à extraire de la réponse JSON du LLM', 'show_if' => ['output_format' => 'json']],
                ]],

            // ── Agrégation multi-lignes ─────────────────────────────────────
            ['type' => 'multiline_aggregate', 'label' => 'Multi-lignes (1-N)', 'group' => 'aggregation', 'icon' => 'icon-th-list',
                'description' => 'Agrège des données depuis des feuilles à plusieurs lignes par produit (ex : B03_MEDIA).',
                'params' => [
                    'filter_type' => ['type' => 'text', 'label' => 'Filtrer par type(s)', 'placeholder' => 'PHOTO ou PHOTO,VIDEO (vide = tout)', 'help' => 'Filtre sur la colonne type_col définie dans les feuilles. Plusieurs types séparés par une virgule.'],
                    'method' => ['type' => 'select', 'label' => 'Méthode', 'width' => '50%',
                        'options' => ['concat' => 'Concaténation (valeurs séparées)', 'first' => 'Premier élément seulement', 'last' => 'Dernier élément seulement',
                            'count' => 'Nombre d\'éléments', 'template' => 'Template personnalisé par ligne', 'json_array' => 'Tableau JSON structuré'],
                        'default' => 'concat'],
                    'separator' => ['type' => 'text', 'label' => 'Séparateur', 'width' => '50%', 'default' => ',', 'placeholder' => ', ou | ou ;', 'help' => 'Caractère(s) entre chaque valeur'],
                    'row_template' => ['type' => 'text', 'label' => 'Template de ligne', 'placeholder' => 'Ex : {N}:{L} → url1:alt1,url2:alt2', 'help' => 'Utilisez {LETTRE} pour insérer une valeur de colonne.', 'show_if' => ['method' => 'template']],
                    'columns' => ['type' => 'columns_mapping', 'label' => 'Mapping des colonnes', 'help' => 'Associe une clé JSON à une lettre de colonne.', 'show_if' => ['method' => ['json_array']]],
                ]],
        ];
    }
}

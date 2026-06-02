# pko/lunar-ai-importer — pipeline import Excel

### 7.quinquies.1 Contexte

Portage du module PrestaShop **Publiko AI Importer** (23 560 lignes de code, 43 actions, parsing Excel multi-feuilles, LLM, staging) vers un package Laravel `packages/pko/ai-importer/` intégré Lunar + Filament. Branche de travail : `ai-importer`. Plan détaillé : `docs/ai-importer-migration-plan.md`.

### 7.quinquies.2 Architecture

- Package **Filament Plugin** autonome `Pko\AiImporter\` sous `packages/pko/ai-importer/`
- 5 tables `pko_ai_importer_*` (configs, llm_configs, jobs, staging, logs)
- Pipeline d'actions **polymorphe** — 21 classes (après simplification Proposition D : `multiply/divide/add/subtract` → alias `math`, `uppercase/lowercase/capitalize` → alias `change_case`). `category_map` et `conditional` restent des actions dédiées (mapping breadcrumb + ternaire simple PS) car non couvertes par `map`/`condition`.
- Workflow découpé en 2 Laravel Jobs queue (`ParseFileToStagingJob`, `ImportStagingToLunarJob`), replacent les 3 cron PS (`cron.php`, `cron-prepare.php`, `cron-import.php`)
- Preview & import via **Filament Resources** natives (pas de DataTables server-side custom) — réutilise les patterns perf TreeManager (`#[Computed]`, pagination SQL, cache Redis progress)

### 7.quinquies.3 Décisions tranchées

| Question | Choix | Raison |
|---|---|---|
| Simplification actions | 43 → 17 (Proposition D de `SIMPLIFICATION.md`) | Les actions PS étaient redondantes. Moins de code, UI cohérente |
| Stockage clé API LLM | Cast Eloquent `encrypted` | Le module PS stockait en clair → faille corrigée |
| Orchestration cron | Laravel Queues + `Bus::batch()` | Aligné stack, resume natif, pas de cron custom |
| Rollback | Backup ciblé tables Lunar concernées (JSON gzippé) plutôt que `spatie/laravel-backup` | Plus léger, plus rapide sur gros imports |
| Édition config JSON v1 | Textarea JSON brut + validation | Phase 5 livrera l'éditeur drag-n-drop Livewire/Alpine |
| UUID job | `HasUuids` Laravel + `uniqueIds()` | Remplace la génération manuelle PS `job_TIMESTAMP_random` |
| Virtual scroll preview | Non — Filament Table pagination SQL | 50 lignes/page suffit, même sur 10 000+ staging rows |
| Progress real-time | Cache Redis + Livewire polling 2s | Pas de Reverb en phase 1 (ajout possible phase 6) |

### 7.quinquies.4 Dépendance externe

- `phpoffice/phpspreadsheet: ^2.0 || ^3.0` — déclaré dans le composer du package. Phase 3 gérera l'itération streaming pour éviter le full-load en mémoire.

### 7.quinquies.5 Intégration avec le reste du projet

- **`mde/catalog-features`** : l'importeur appelle `Features::syncByHandles($product, [...])` à la fin de chaque row importée pour mapper les caractéristiques filtrables
- **Prix Lunar** : toujours écrits en **cents entiers** via `Price::updateOrCreate([...])`, jamais en float
- **`attribute_data`** : toujours assemblé comme collection de `Lunar\FieldTypes\*` (TranslatedText, Number…), jamais de strings bruts
- **Stock** : écrit sur `ProductVariant::stock` (int), pas de table dédiée — diffère du modèle PS `ps_stock_available`
- **Images** : Spatie MediaLibrary (`$product->addMediaFromUrl(...)`) — déjà utilisé par Lunar

### 7.quinquies.6 Navigation

Nouveau groupe Filament **« Imports »** (entre *Expédition* et *Configuration*) avec 3 Resources :

- `ImportJobResource` — liste des imports, création (upload + config + options), détail (preview staging + logs)
- `ImporterConfigResource` — CRUD configs de mapping par fournisseur
- `LlmConfigResource` — CRUD clés API LLM (Claude, OpenAI)

### 7.quinquies.7 Phase 1 — Foundation (commit 4a66a3e)

- Squelette package complet, 5 migrations appliquées, 5 modèles Eloquent, 6 enums, contrats, 16 classes d'action, LLM Manager + providers, 2 Jobs queue (squelettes), 3 Filament Resources, enregistrement `composer.json` + `bootstrap/providers.php` + `AppServiceProvider`.

### 7.quinquies.8 Phases 2-6 livrées (ce commit)

#### Phase 3 — Parsing multi-feuilles

- **`Services/SpreadsheetParser`** : lecteur PhpSpreadsheet (XLSX/XLS/CSV). Itération générateur sur la feuille primaire, pré-indexation paresseuse des feuilles secondaires par `join_key`. Chaque ligne est exposée en double clé : par nom d'en-tête ET par lettre de colonne (`A`, `B`, `M`...), pour rester rétro-compatible avec les configs PS qui pointent en lettres.
- **`Services/ProgressCache`** (Redis) : clé `ai-importer:job:{uuid}:progress` contenant `{processed, total, percentage, updated_at}`, TTL 15 min. Alimenté par les jobs toutes les `checkpoint_every` lignes, lu par Livewire polling (évite un SELECT DB toutes les 2 s).
- **`Jobs/ParseFileToStagingJob`** (réel, plus un stub) : lit le fichier, construit l'`ExecutionContext` par ligne (row + secondary sheets indexed), fait tourner `ActionPipeline` pour chaque colonne du mapping, écrit `StagingRecord` avec `status=pending`. Transition statut `pending → parsing → parsed/error` + écriture dans `ImportLog`.
  - **Isolation par ligne** : la boucle enveloppe chaque ligne dans son propre `try/catch`. Une ligne fautive (action mal formée, échec LLM, cellule invalide) ne fait **jamais** avorter tout le job — elle est journalisée (`ImportLog` niveau Error avec `row_number`), persistée en staging au statut `error` (data partielle), puis l'`error_policy` du job décide : `ignore` → continue ; `stop`/`rollback` → arrêt propre du parse (`status=error`, rien à rollback à ce stade puisque aucune écriture Lunar). Le `try/catch` global ne couvre plus que les erreurs fatales (fichier illisible, feuille absente).
  - **Sémantique de reprise (`last_processed_row`)** : compteur de **lignes data traitées** (succès OU erreur), et non l'index absolu de feuille. Passé tel quel à `SpreadsheetParser::iterateRows($sheet, $startAfterRow)` qui l'interprète comme un offset de comptage. « Relancer le parse » (`ViewImportJob::resumeParse`) reprend ainsi exactement après la dernière ligne traitée, sans saut ni doublon. (Avant : double-comptage `startAfter + processed` alors que `processed` était déjà initialisé à `startAfter`.)
  - **Anti-doublon à la reprise** : index UNIQUE `(import_job_id, row_number)` sur `pko_ai_importer_staging` (migration `2026_06_02_100000_*`, remplace l'index non-unique) + `StagingRecord::updateOrCreate` sur cette clé. Si un checkpoint était en retard sur les écritures réelles lors d'un crash, la reprise réécrit la ligne au lieu d'en créer une seconde. Le compteur `staging_count` n'est incrémenté que sur `wasRecentlyCreated`.
- **Dépendance ajoutée** : `phpoffice/phpspreadsheet: ^3.0` au composer racine (PhpSpreadsheet 3.x — API stable, PHP 8.2+).

#### Phase 4 — Écriture Lunar + backup/rollback

- **`Services/LunarProductWriter`** : écriture d'un `StagingRecord` vers `Product` + `ProductVariant` + `Price` + `Collection` + `Brand` + custom Features. Résolution par SKU (`reference`). Cache par instance pour brand et collection lookups. Contrat de clés staging documenté in-code — voir la docblock de la classe pour la liste exhaustive (17 clés reconnues).
- **`attribute_data`** assemblé comme Collection de `Lunar\FieldTypes\TranslatedText` pour `name`, `description`, `description_short`, `meta_title`, `meta_description`, `meta_keywords` + `Text` pour `url_key`. Préserve les traductions existantes sur update (merge par langue).
- **Prix** : `Price::updateOrCreate` keyé sur `(priceable_type, priceable_id, currency_id, customer_group_id=null, min_quantity=1)`, valeur en cents entiers. Support `compare_price_cents`.
- **Features catalogue** : `Pko\CatalogFeatures\Facades\Features::syncByHandles()` via `class_exists` guard (le writer reste utilisable même si catalog-features est désinstallé).
- **`Services/LunarBackupManager`** : snapshot avant import. Collecte les SKUs présents en staging → récupère les `ProductVariant` correspondants + leurs produits + prix + pivot `lunar_collection_product`. Stockage `storage/app/ai-importer/backups/job_{uuid}_{datetime}.json.gz` (gzip level 6, typiquement 70-80% compression). Format JSON brut, lisible à l'œil. `restore()` replay en transaction.
- **`Jobs/ImportStagingToLunarJob`** (réel) : boucle `chunkById(200)` sur staging (statuts `pending|validated|warning`), appelle le writer par row, gère les 3 politiques d'erreur :
  - `ignore` → log + continue
  - `stop` → retourne false depuis `chunkById`, transition `import_status=error`
  - `rollback` → `LunarBackupManager::restore()` + `import_status=rolled_back`
- Snapshot créé UNE fois au premier run. Sur resume, le backup_path existant est réutilisé.

#### Phase 5 — UI Filament (RelationManagers + Actions)

- **`StagingRecordsRelationManager`** : tableau preview avec colonnes `row_number / SKU / nom / prix formaté / statut badgé / erreur`. Filtre par statut, search sur JSON (`where data like %"reference":"%Q%"`). Edit modale avec textarea JSON + validation `json`. Bulk actions : valider / ignorer / supprimer.
- **`ImportLogsRelationManager`** : tableau read-only, polling 5s, tri `id desc`, filtre par niveau, badge coloré. Fournit la vue temps-réel pendant le parse/import.
- **Header actions sur `ViewImportJob`** :
  - `Resume parse` — visible si `Paused | Error`
  - `Launch import Lunar` — visible si `Parsed` et `import_status ∈ {Pending, Scheduled}`
  - `Rollback` — visible si `Imported` avec `backup_path` et `!rollback_completed`
  - `Cancel` — visible si `Pending | Parsing | Paused`
- L'éditeur visuel structuré de config est livré en V2 (cf §7.quinquies.13). Le textarea JSON brut reste disponible en second onglet comme échappatoire avancée.

#### Phase 6 — CLI migration depuis PS

- **`php artisan ai-importer:import-ps-config {file} [--name=] [--supplier=] [--replace]`** : lit un JSON Publiko AI Importer et crée un `ImporterConfig`. Compatibilité v0 : si une colonne a `action:{}` (objet unique legacy) au lieu de `actions:[]`, la commande le lift automatiquement. Affiche un résumé : colonnes mappées / actions totales / feuilles.

#### Phase 2 — Tests

- **`tests/Unit/AiImporter/Actions/ActionTypesTest`** (17 tests) : chaque type d'action (math, change_case, truncate, concat, template, map simple et multi-value, validate_ean13, slugify, replace/regex_replace, copy, trim, date_format, multiline_aggregate concat et count) + couverture `concat`/`template` avec `sources` en objet `{col, sheet}` (mono/multi-feuilles, mix string+objet, fallback row primaire).
- **`tests/Unit/AiImporter/Services/ActionPipelineTest`** (9 tests) : chaînage ordonné, défaut sur valeur null, `condition` true/false, lève sur type inconnu, `prefix`/`suffix` colonne appliqués à la valeur source (avant actions, no-op si vide), condition `col_value` évaluée sur la valeur **brute** (non préfixée).
- **`tests/Feature/AiImporter/ParseFileToStagingJobTest`** : CSV fake → pipeline → staging (fixture CSV minimale via `Storage::fake`). Couvre aussi la robustesse parse : `error_policy=ignore` (ligne fautive → statut `error`, le job continue), `error_policy=stop` (arrêt propre sur la 1re erreur, lignes suivantes non parsées), et la reprise sans doublon (`row_limit` puis relance → reprend exactement après `last_processed_row`, `updateOrCreate` idempotent). Une `BoomAction` de test (registrée via `ActionRegistry::register`) lève sur une cellule sentinelle pour simuler l'échec déterministe.
- **`tests/Feature/AiImporter/LunarProductWriterTest`** (3 tests) : création produit + variant + prix, update sur 2e appel, erreur sur `reference` manquante.

Couverture totale : **26 nouveaux tests verts** (61 total sur le projet).

### 7.quinquies.9 Contrat du writer (clés staging reconnues)

Les clés suivantes, si présentes dans `StagingRecord::data`, déclenchent une écriture Lunar :

| Clé | Cible Lunar | Notes |
|---|---|---|
| `reference` **(requis)** | `ProductVariant::sku` | Résolveur : existe ? update. Sinon : create. |
| `name` **(requis en création)** | `attribute_data.name` (TranslatedText) | Stocké dans la langue par défaut |
| `description` | `attribute_data.description` (TranslatedText) | |
| `description_short` | `attribute_data.description_short` | |
| `meta_title` / `meta_description` / `meta_keywords` | `attribute_data.*` (TranslatedText) | |
| `url_key` | `attribute_data.url` (Text) | |
| `ean` | `ProductVariant::ean` | |
| `stock` | `ProductVariant::stock` (int) | |
| `price_cents` | `Price::price` (cents) | UpdateOrCreate keyé sur variant+currency+tier |
| `compare_price_cents` | `Price::compare_price` (cents) | Optionnel |
| `weight_value` | `ProductVariant::weight_value` (kg) | Unité forcée à `kg` |
| `length_value` / `width_value` / `height_value` | `ProductVariant::{axis}_value` (cm) | Unité forcée à `cm` |
| `brand_name` | `Product::brand_id` | `Brand::firstOrCreate(['name' => ...])` |
| `collections` | `Product::collections()` | Array ou CSV, int (ID) ou string (handle). `syncWithoutDetaching` |
| `features` | pivot `pko_feature_value_product` | Hash `{family_handle => [value_handle, ...]}`, delegated à `catalog-features` |
| `images` | Spatie MediaLibrary | Array ou CSV d'URLs distantes. Idempotent via `custom_properties.source_url`, première URL `primary=true`. |
| `videos` | `pko/product-videos` | Array ou CSV d'URLs YouTube/Vimeo/Dailymotion/MP4. Idempotent par URL. |
| `product_type_handle` | ProductType | Lookup par handle, fallback sur premier trouvé |
| `tax_class_handle` | TaxClass | Lookup par handle, fallback sur premier trouvé |
| `compare_price_cents` | `Price::compare_price` | Prix barré |

Les clés inconnues du writer sont **ignorées silencieusement** — le config author peut donc émettre des keys arbitraires pour d'autres consommateurs downstream.

#### Aliases legacy PrestaShop FAB-DIS (auto-normalisés par `LunarProductWriter::normalizeLegacyKeys`)

Pour importer un JSON Publiko AI Importer (PrestaShop) tel quel, sans renommer les clés du mapping, le writer accepte ces alias et les remappe vers la clé canonique ci-dessus. **La clé canonique gagne toujours** quand les deux sont présentes — l'alias est ignoré.

| Alias PS | Clé canonique | Conversion |
|---|---|---|
| `ean13` | `ean` | — |
| `quantity` | `stock` | cast int |
| `manufacturer` | `brand_name` | — |
| `link_rewrite` | `url_key` | — |
| `width` | `width_value` | cast float |
| `height` | `height_value` | cast float |
| `depth` | `length_value` | cast float (axes : depth PS = length Lunar) |
| `weight` | `weight_value` | cast float |
| `image` | `images` | passe array ou CSV inchangé |
| `category` | `collections` | passe array ou CSV inchangé |
| `price_tex` | `price_cents` | **×100 puis `(int) round()`** (euros → cents) |

### 7.quinquies.10 Actions disponibles (21 + 7 alias legacy)

| Type | Rôle |
|---|---|
| `math` | Opération arithmétique (`operation: multiply|divide|add|subtract`) |
| `round` | Arrondi à N décimales |
| `change_case` | upper / lower / capitalize |
| `trim` | both / left / right |
| `truncate` | Coupe à N caractères avec suffix optionnel |
| `slugify` | URL-safe (kebab-case) |
| `prefix` | Préfixe une string littérale (avec separator optionnel) |
| `suffix` | Suffixe une string littérale |
| `replace` | Search/replace littéral |
| `regex_replace` | Search/replace via pattern PCRE |
| `date_format` | Conversion entre patterns de date |
| `validate_ean13` | Valide checksum EAN13 (sinon vide) |
| `concat` | Concatène plusieurs `sources` — voir **Sources multi-feuilles** ci-dessous |
| `template` | Interpole `{placeholders}` depuis `sources` nommées — voir **Sources multi-feuilles** ci-dessous |
| `copy` | Recopie une autre colonne (déjà mappée ou brute) |
| `map` | Lookup `{from => to}`, support multi-value avec séparateur |
| `category_map` | Mappe un libellé via `values` → fil d'Ariane, fallback `default_category`, puis CSV de handles (réutilise `parse_category_breadcrumb`). Config PS `somfy.json` |
| `llm_transform` | Appel LLM (Claude/OpenAI) avec prompt + sources |
| `multiline_aggregate` | Agrège plusieurs lignes d'une sheet many (concat/count/json_array). `filter_type` restreint sur la colonne `type_col` (défaut `type`, ex PS `MTYP`). |
| `feature_build` | Construit le hash `features` depuis N colonnes source |
| `parse_features_string` | Parse `"F1:V1,V2|F2:V3"` (sortie LLM) → `{f1:[v1,v2]}` slugifié |
| `parse_category_breadcrumb` | Parse `"A>B>C,D>E"` → CSV de handles, mode `leaf` (défaut) ou `all` |
| `condition` | Branching `if/else` avec branches/rules/else_actions. Support `field: "sheet:col"` ou `"col_value"`, opérateurs `=`/`!=`/`>`/`<`/`contains`/`empty`/`in`. Premier match wins. Pas de récursion (nested condition silencieusement skip). |
| `conditional` | Ternaire simple PS `{condition:"> 0", if_true:"1", if_false:"0"}`. Parse l'opérateur en tête (`>`/`>=`/`=`/`!=`/`contains`/`empty`…) sur la valeur courante. Distinct de `condition` (pas de branches/actions imbriquées). |

**Alias legacy** (configs PrestaShop v0) :
- `multiply`, `divide`, `add`, `subtract` → `MathAction` (opération correspondante).
- `uppercase`, `lowercase`, `capitalize` → `ChangeCaseAction` (mode `upper`/`lower`/`capitalize` dérivé du type).

Permet d'importer un JSON PS sans modifier les actions.

**Tolérance aux clés inconnues** : `Action::fromArray()` (factory de base) filtre la config pour ne garder que les clés correspondant à un paramètre du constructeur de l'action ciblée (via `ReflectionClass::getConstructor()`). Les clés extra — `comment` (annotation documentaire fréquente dans les configs PrestaShop réelles), marqueurs internes `_*`, etc. — sont **ignorées silencieusement** au lieu de lever `ArgumentCountError: Unknown named parameter`. La même tolérance s'applique à `MathAction::fromArray()` (qui override la factory) via le helper protégé `Action::filterConstructorParams()`.

#### Sources multi-feuilles (`concat` / `template`)

Les actions `concat` et `template` acceptent chaque entrée de `sources` sous **deux formes** (cf. `config/example-avec-actions.json` du module PS, cas FAB-DIS) :

- **String** → clé de colonne lue dans la **row primaire** (`ctx->row`). Forme historique, conservée pour rétrocompat.
- **Objet `{"col": "...", "sheet": "..."}`** → colonne lue dans la **première row jointe** d'une feuille secondaire (`ctx->sheets[sheet][0][col]`).

```json
{ "type": "concat",   "separator": ",", "sources": [
    {"col": "URL_IMAGE_1", "sheet": "B03_MEDIA"},
    {"col": "URL_IMAGE_2", "sheet": "B03_MEDIA"}
] }

{ "type": "template", "template": "{nom} — {marque}", "sources": {
    "nom":    {"col": "LIBELLE", "sheet": "B01_COMMERCE"},
    "marque": "MARQUE_COL"
} }
```

Résolution (trait `Actions\Concerns\ResolvesSheetSources`, alignée sur `ParseFileToStagingJob` et `ConditionAction::resolveField`) :
- `sheet` vide/absent, ou feuille secondaire **sans row jointe** pour cette ligne → fallback sur `ctx->row[col]` (chaîne vide si la colonne n'existe pas — pas de crash).
- `sheet` présent dans `ctx->sheets` (feuille secondaire jointe) → `ctx->sheets[sheet][0][col]`.
- Relation `many` : seule la **première** row jointe est lue. Pour agréger toutes les rows, utiliser `multiline_aggregate`.

### 7.quinquies.11 Diagnostic des imports LLM

Quand un `llm_transform` (ou n'importe quelle source) émet un handle de `Collection` ou de `FeatureFamily`/`FeatureValue` qui n'existe pas en DB, le writer **ne plante pas** : le record est écrit normalement, et un `ImportLog` warning est créé avec le détail des handles ignorés. Visible dans l'onglet **Logs** de chaque `ImportJob` (RelationManager temps-réel).

Exemple de message :

```
[SKU-12345] handle(s) introuvable(s) — 2 collection(s), 3 feature(s) ignoré(s).
context: {
  collections: ["categorie-inexistante", "autre-cat"],
  features: ["family:application", "value:matiere.unobtanium"]
}
```

Stratégie : afficher la liste des handles autorisés au LLM via `llm_global_context` (cf JSON Publiko AI Importer). Pour les rares hallucinations restantes, l'admin diagnostique via les logs et choisit : créer la catégorie/feature manquante, ou affiner le prompt.

### 7.quinquies.12 Limitations connues

- ProductType : le writer utilise le premier `ProductType` trouvé (ordre `id asc`) si `product_type_handle` absent. Pour forcer, ajouter la clé au staging.
- TaxClass : idem, premier trouvé (fallback de `tax_class_handle`).
- True streaming XLSX : `PhpSpreadsheet::load()` charge tout en RAM. Au-delà de ~100k lignes, basculer sur un `IReadFilter` chunked — l'API parser reste stable.
- Éditeur config visual : livré (cf §7.quinquies.13). Filtre live « masquer colonnes vides » / recherche de colonne au niveau du Repeater de mapping non implémenté (Filament 3.3 n'expose pas de filtrage d'items natif) — utiliser la recherche du dropdown « Champ cible ».
- Appels LLM réels non testés automatiquement (nécessitent une clé API valide, hors CI).

### 7.quinquies.13 Éditeur de configuration V2 (fidélité PrestaShop)

`ImporterConfigResource` reproduit l'éditeur du module Publiko AI Importer en thème Filament/Lunar. Onglet **Éditeur visuel** (3 sections) + onglet **JSON brut** (échappatoire). Les deux sérialisent vers la même colonne `config_data`.

**Sections de l'éditeur visuel :**
1. **Feuilles Excel** — Select **Type de source** (`FAB-DIS` / `CSV` / `XLSX`, mappé sur `config_data.type` — repris par `saved-configs.blade.php` pour le badge Type), puis `primary_sheet` + `join_key` globale, puis Repeater « Feuilles avec relations » (nom, relation `one`/`many`, colonne de jointure, type, toggle en-têtes). Mappe sur `config_data.sheets{}`.
2. **Configuration IA (optionnel)** — toggle `ai.context_cache` + textarea `ai.global_context`. Le bloc `ai` est **omis du JSON** si inutilisé (cache off + contexte vide), via `normalizeAi()`.
3. **Mapping des colonnes** — Repeater présenté en grille tabulaire (Filament 3.3 n'a pas encore `Repeater::table()`) : par ligne un **champ cible** (dropdown groupé `ProductFieldCatalog`), colonne source, feuille, valeur par défaut, et un **résumé compact du pipeline**. Bouton **« Configurer »** = action modale (`extraItemActions`, largeur `5xl`) éditant le pipeline d'actions de la ligne.

**Builder de pipeline (modal) :**
- Repeater d'actions ordonné, type d'action choisi via **Select catégorisé** (`ActionPalette::groupedOptions()` — Logique / Calcul / Texte / Remplacement / Combiner / Correspondance / Dates & validation / IA / Agrégation). Tout type runtime absent de la palette curatée est surfacé dans un groupe « Autres » (pas de perte silencieuse).
- Paramètres génériques via `KeyValue` (visible pour tout type sans éditeur dédié, c.-à-d. ≠ `condition`/`llm_transform`/`map`).
- Type `condition` : **builder de branching SI / ALORS / SINON SI / SINON**. Repeater de branches (logique ET/OU, règles `field`/`operator`/`value`, actions ALORS) + actions SINON (`else_actions`). Les pipelines internes (branche / sinon) **n'autorisent pas** de `condition` imbriquée — cohérent avec `ConditionAction` qui ne récurse jamais.
- Type `llm_transform` : **éditeur de prompt IA dédié** (Fieldset « Prompt IA ») — Select `llm_config_id` (options depuis `Pko\AiCore\Models\LlmConfig`, vide = config par défaut), Textarea `prompt`, TagsInput `input_columns`, Select `output_format` (`string`/`json`), TextInput `output_json_key` (visible si `json`), Textarea `additional_context`. Remplace le `KeyValue` brut, peu lisible pour un prompt.
- Type `map` : **table de correspondance dédiée** (Fieldset « Table de correspondance ») — `KeyValue` alimentant le param `values` (valeur source → valeur cible), TextInput `default`, toggle `multi_value` + TextInput `separator` (visible si multi). Remplace la saisie JSON manuelle de `values`.
- Les éditeurs `llm_transform` et `map` sont disponibles aussi bien au niveau racine du pipeline que dans les branches `condition`.

**Round-trip JSON↔visuel (`hydrateVisual` / `dehydrateVisual`) :**
- Clés scratch `sheets_repeater` / `mapping_repeater` pendant l'état du formulaire, repliées sur `sheets{}` / `mapping{}` au save (et omises si vides → pas d'artefact `[]`).
- Les actions **sans éditeur dédié** conservent la représentation éprouvée `{type, params:KeyValue}` (typage restauré par `typedParams` : bool/int/float/JSON) → **zéro régression** des types génériques + alias. `condition`, `llm_transform` et `map` reçoivent chacun une branche hydrate/dehydrate dédiée (champs nommés → forme canonique `{type, ...params}`), lue/écrite nativement depuis le JSON canonique. `map` ré-émet toujours `values`/`default`/`multi_value`/`separator` (round-trip sans perte) ; `llm_transform` n'émet que les champs renseignés (`output_format` toujours présent).
- Garde-fou : `tests/Unit/AiImporter/ImporterConfigRoundTripTest` couvre feuilles, IA, type de source, pipeline simple, `map` (params JSON + multi-valeur), `llm_transform` complet (`json` + `output_json_key` + `additional_context`), branching `condition` complet, et l'ensemble des types d'actions enregistrés.

**Classes support :** `Pko\AiImporter\Support\ProductFieldCatalog` (champs produit canoniques type PrestaShop, clés = clés moteur consommées par `LunarProductWriter`) et `Pko\AiImporter\Support\ActionPalette` (palette catégorisée + labels FR, source unique pour le Select et le résumé). Branding neutre (aucun nom client).

### 7.quinquies.14 V1 fidélité PrestaShop — socle backend

Rapprochement du module PrestaShop d'origine. **Backend only** — l'UI Filament de ces options est documentée en §7.quinquies.13.

#### Feuilles avec relations (`config_data.sheets`)

Schéma porté du JSON Publiko AI Importer. Chaque feuille secondaire déclare :

| Clé | Rôle |
|---|---|
| `relation` | `one` (jointure 1-1) ou `many` (jointure 1-N). Défaut `many`. |
| `join_col` | Colonne de jointure **côté feuille secondaire** (fallback `join_key` local puis `join_key` global). |
| `type_col` | Nom de la colonne « type » d'une feuille `many`, consommée par `multiline_aggregate.filter_type` (défaut `type`, ex PS `MTYP`). |
| `skip_first_row` | inchangé. |

- `ParseFileToStagingJob` résout la valeur initiale d'un mapping selon sa clé `sheet` :
  feuille primaire (ou absente) → row courante ; feuille `relation=one` → unique row
  jointe (`$sheets[$sheet][0]`) ; feuille `relation=many` → laissée à `multiline_aggregate`.
- `SpreadsheetParser::secondarySheetsFor()` indexe désormais sur `join_col` en priorité.

#### Clés de mapping de colonne (`config_data.mapping.<clé>`)

Lues par `ActionPipeline::run()` dans l'ordre suivant :

| Clé | Rôle |
|---|---|
| `col` / `sheet` | Colonne source (+ feuille). |
| `default` | Valeur de repli si la source est `null`. |
| `condition` `{field, operator, value}` + `else` | Gate optionnel. `field: "col_value"` évalue la valeur **brute** de la colonne (avant `prefix`/`suffix`, comme le `getColumnValue()` du module PS) ; sinon `field` cible une autre colonne de la row. Échec → retourne `else` (ou la valeur, non affixée). |
| `prefix` / `suffix` | Concaténation simple (sans séparateur) sur la valeur source **non vide**, appliquée **avant** la pipeline d'actions (port du `prefix` au niveau colonne des mappings PS, ex `{"col":"REFCIALE","prefix":"FAA"}`). No-op si la valeur est `null`/vide. |
| `actions[]` | Pipeline d'actions séquentiel. |

#### Section IA (`config_data.ai`)

| Clé | Type | Effet |
|---|---|---|
| `global_context` | string | Injecté dans **tous** les appels LLM en bloc système (`LlmTransformAction`). |
| `context_cache` | bool | Si vrai, le contexte global est émis avec `cache_control: ephemeral` (Anthropic prompt caching). Sans effet sur OpenAI (caching serveur auto). |

Câblage : `LlmTransformAction` lit `job->config->config_data['ai']` et passe
`system` + `cache_system` en options de `transform()`. `ClaudeProvider` ajoute un
bloc `system` (texte simple, ou bloc cache_control si `cache_system`). `OpenAiProvider`
préfixe un message `role=system`.

#### Options de job (`pko_ai_importer_jobs.options`, JSON)

Honorées par les jobs + le writer. Enums dédiés `UpdateMode` / `RowFilter` (labels FR + couleurs).

| Option | Valeurs | Où | Comportement |
|---|---|---|---|
| `update_mode` | `all` \| `price` \| `stock` \| `price_stock` | `LunarProductWriter` | Sur un produit **existant** uniquement : restreint les champs écrits (`price` → prix seul, `stock` → stock seul, `price_stock` → les deux, `all` → tout). La **création** reste toujours intégrale. Porte le « Si le produit existe déjà » de PS. |
| `row_filter` | `all` \| `missing_supplier_ref` \| `existing_supplier_ref` | `LunarProductWriter` | Filtre selon la présence en base (via `join_column`). `missing` → n'écrit que les créations ; `existing` → n'écrit que les MAJ ; les lignes exclues passent en statut `skipped`. |
| `join_column` | `reference` (défaut, → SKU) \| `ean`/`ean13` (→ EAN) | `LunarProductWriter` | Colonne d'identification du produit existant. ⚠️ Lunar n'a **pas** de champ « réf fournisseur » natif : mappez votre réf fournisseur sur `reference` (SKU) dans le mapping de config. |
| `columns_to_process` | array de clés de mapping | `ParseFileToStagingJob` | Restreint le mapping calculé au parse (les autres clés sont ignorées). Vide = tout. |
| `columns_to_import` | array de clés staging | `LunarProductWriter` | Restreint les champs réellement écrits dans Lunar. Les clés essentielles (`reference`, `name`, `join_column`) sont toujours conservées. Vide = tout. |

`LunarProductWriter::configure(array|ArrayObject|null $options): self` applique ces
options une fois par job (appelé dans `ImportStagingToLunarJob` depuis `$job->options`).

Tests : `tests/Feature/AiImporter/WriterOptionsTest` (7 cas — update_mode × 4, row_filter × 3).

### 7.quinquies.14 V3 — Page « Préparer un fichier » (UI Filament fidèle PS)

Portage fidèle de la page PrestaShop *Prepare a File* sur `ImportJobResource` (page
**Create** = « Préparer un fichier », page **List** = « Liste des imports »).

#### `Support/ConfigColumnExtractor` (helper testable)

Extrait du `config_data.mapping` la liste des **colonnes à traiter** affichées dans
la grille, à l'identique de `ajaxProcessGetConfigColumns` (PS) :

- ignore les colonnes sans `col` source **ET** sans `actions`/`action` (valeur
  `default` seule = statique, rien à préparer) ;
- `has_ai = true` dès qu'une action est de type `llm_transform` (gère aussi le
  format legacy v0 `action: {}` objet unique) ;
- libellé = `clé (sheet:col)` / `clé (colonne X)` / `clé (défaut: …)`, tri alpha ;
- helpers `allColumnKeys()` (pré-cochage), `aiColumnKeys()` (bouton « désélectionner IA »).

Tests : `tests/Unit/AiImporter/Support/ConfigColumnExtractorTest` (5 cas, pur, sans DB).

#### Page Create (`CreateImportJob`) — sections du formulaire

1. **Configurations enregistrées** (section repliable) : table inline rendue par la
   vue `pko-ai-importer::filament.saved-configs` (namespace de vues ajouté au
   `ServiceProvider` via `loadViewsFrom`). Colonnes Fournisseur / Type / nb Colonnes /
   Actions. Éditer → lien `ImporterConfigResource`. Dupliquer / Supprimer → `wire:click`
   sur les méthodes publiques `duplicateImporterConfig()` / `deleteImporterConfig()` de
   la page. La requête des configs est faite **dans la vue** pour refléter en direct
   les duplications/suppressions.
2. **Header actions** : « Nouvelle configuration » (→ create config), « Importer JSON »
   (modale nom optionnel + upload → délègue à la commande `ai-importer:import-ps-config`
   avec `--replace`), « Exécuter CRON » (lance `ai-importer:run-scheduled` via
   `Artisan::call`, notification avec la sortie).
3. **Colonnes à traiter** : `CheckboxList` `options.columns_to_process` générée live
   depuis la config choisie (`config_id` `->live()`), badges HTML « AI Prompt »
   (`allowHtml`), `bulkToggleable()` (tout sélectionner / désélectionner) + `hintAction`
   « Désélectionner les colonnes IA ». Pré-cochage de toutes les colonnes à chaque
   changement de config (`afterStateUpdated`).
4. **Filtrage des lignes** : `Radio` `options.row_filter` (enum `RowFilter`) +
   `Select` `options.join_column` (Référence/EAN) affiché si filtre ≠ « toutes ».
5. **Limite de lignes** (`row_limit`) + **Taille du lot** (`chunk_size`) + politique
   d'erreur + planification, libellés/aides FR alignés sur PS.
6. Bouton submit relabellé **« Préparer les données »** ; « créer un autre » masqué ;
   `afterCreate` dispatch `ParseFileToStagingJob` (inchangé).

> Les champs `options.*` sont persistés dans la colonne JSON `options` (cast
> `AsArrayObject`) via la notation pointée Filament — consommés par
> `ParseFileToStagingJob` (`columns_to_process`) et `LunarProductWriter` (`row_filter`,
> `join_column`), cf. §7.quinquies.13.

#### Page List (`ListImportJobs`) — colonnes alignées PS

Table : Job (UUID mono) · **Source** (badge Préparation/Import CSV selon `config_id`) ·
Configuration · **Fichier** (`basename(input_file_path)`) · **Préparation** (`status`) ·
**Import** (`import_status`) · **Progression** (`processed/total (max N)`) · Date.
Actions par ligne : Voir / Logs (→ page view) / Supprimer. Header : « Préparer un
fichier » + « Importer une préparation ».

> **Limite assumée** : PS exposait « Importer une préparation » comme un chemin séparé
> (CSV déjà transformé → staging direct). Ce portage unifie tout via le flux
> « Préparer un fichier » + config ; le bouton renvoie donc vers la page Create (tooltip
> explicatif). Un vrai chemin CSV-préparé→staging sans config reste hors scope V3.
>
> L'équivalent du « Run CRON » PS est la commande `ai-importer:run-scheduled`
> (planifiée), exposée en plus en action UI sur la page Create.

> **Note dette technique (hors scope ce volet)** : la suite complète sur DB fraîche
> (`migrate:fresh` de `RefreshDatabase`) échoue à cause d'un conflit de migration
> inter-packages — `page-builder` (`add_content_to_cms_tables`) et `storefront-cms`
> (`unify_posts_and_pages`) ajoutent tous deux la colonne `content` à `pko_posts`,
> la seconde sans garde `hasColumn`. À corriger côté storefront-cms.

#### V4 — Page « Aperçu & Import » (portage `preview.tpl` PS)

Refonte de la page détail d'un job (`ViewImportJob` + `StagingRecordsRelationManager`)
pour reproduire la page PrestaShop « Aperçu et Import », thème Filament natif.

- **Stat cards** (`ImportJobProgressWidget`, polling 2 s) : `Parse` + 5 compteurs de
  staging — Total / En attente / Importé / Avertissements / Erreurs. Source :
  `ImportJob::stagingStatusCounts()` (une requête `GROUP BY status`, buckets :
  `pending`=pending+validated, `imported`=imported+created+updated, `warning`,
  `error`, `skipped`). Testable sans Filament.
- **Infolist** (3 sections, `ViewImportJob::infolist()`) :
  - *Fichiers joints* — vue `pko-ai-importer::filament.infolists.attached-files`
    (fichier source / traité / sauvegardes scannées dans le disque par `job_<uuid>_`).
    Actions de section : télécharger source, télécharger sauvegarde active,
    restaurer (= `LunarBackupManager::restore`, expose le rollback par la section
    en plus du header action).
  - *Options d'import* — affichage `join_column`, `update_mode`, politique d'erreur,
    `scheduled_at`, `columns_to_import`. Édition via le header action **« Options
    d'import »** (modal : `TextInput` join_column, `Radio` update_mode, `Select`
    error_policy, `DateTimePicker` scheduled_at, `CheckboxList` columns_to_import
    alimentée par `ProductFieldCatalog::flat()`). Persistées dans `$job->options`
    + colonnes `error_policy`/`scheduled_at`, consommées au lancement par
    `ImportStagingToLunarJob` → `LunarProductWriter::configure()`.
  - *Logs de console* — vue terminal `pko-ai-importer::filament.infolists.console-logs`
    (300 derniers `ImportLog`, niveaux colorisés), `wire:poll.3s` tant que le job
    est en cours. **Complète** `ImportLogsRelationManager` (conservé pour le filtrage).
- **Header actions** : existantes (resumeParse, launchImport, rollback, resumeImport,
  cancel) + **editOptions** + **testCron** (= `Artisan::call('ai-importer:run-scheduled',
  ['--dry' => true])`, sortie en notification — équivalent du « Tester CRON » PS).
- **Aperçu des données** (`StagingRecordsRelationManager`) : statut éditable en ligne
  (`SelectColumn`), modal d'édition de ligne complète avec **« Historique des logs de
  la ligne »** (`ImportLog` filtrés sur `row_number`), action header **Exporter CSV**
  (flux `row_number,status,error_message,<clés data>` aplaties). Filtre par statut +
  recherche SKU + pagination déjà en place.

> **Limite connue** : Filament n'offre pas d'édition de cellule au double-clic native.
> Le statut est éditable inline (`SelectColumn`) ; l'édition complète d'une cellule
> passe par le modal de ligne. La fusion (« Merger ») PS n'est pas portée (optionnelle).

Tests : `tests/Feature/AiImporter/ViewImportJobTest` (compteurs de staging × 2 +
édition d'une ligne staging via le relation manager). Même réserve `RefreshDatabase`
que ci-dessus (conflit migration `pko_posts`).

---


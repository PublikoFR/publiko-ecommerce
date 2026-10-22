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

- **`pko/lunar-catalog-features`** : l'importeur appelle `Features::syncByHandles($product, [...])` à la fin de chaque row importée pour mapper les caractéristiques filtrables
- **Prix Lunar** : toujours écrits en **cents entiers** via `Price::updateOrCreate([...])`, jamais en float
- **`attribute_data`** : toujours assemblé comme collection de `Lunar\FieldTypes\*` (TranslatedText, Number…), jamais de strings bruts
- **Stock** : écrit sur `ProductVariant::stock` (int), pas de table dédiée — diffère du modèle PS `ps_stock_available`
- **Images** : Spatie MediaLibrary (`$product->addMediaFromUrl(...)`) — déjà utilisé par Lunar

### 7.quinquies.6 Navigation

Nouveau groupe Filament **« Imports »** (entre *Expédition* et *Configuration*) avec 3 Resources :

- `ImportJobResource` — liste des imports, création (upload + config + options), détail (preview staging + logs)
- `ImporterConfigResource` — CRUD configs de mapping par fournisseur
- `LlmConfigResource` — CRUD clés API LLM (Claude, OpenAI)

### 7.quinquies.6bis Import direct d'un CSV préparé (parité PS read-only)

Header action **« Importer un CSV préparé »** sur la page `CreateImportJob` (modale : nom optionnel + upload CSV). Portage de `AdminPublikoImportController::ajaxProcessImportPreparedCsv` (PrestaShop) : on charge un CSV **déjà préparé** directement en staging, **sans `ImporterConfig` ni traitement IA**.

- Service dédié **`Services/PreparedCsvImporter`** : lecture `fgetcsv` (séparateur `;`, BOM UTF-8 strippé sur la 1ʳᵉ cellule d'en-tête), 1ʳᵉ ligne = en-têtes → clés du tableau `data` de chaque `StagingRecord`. Lignes vides ignorées.
- Crée un `ImportJob` **sans `config_id`** (badge « Import CSV » côté liste, cf. colonne `source`), directement en `status=Parsed` / `import_status=Pending` (aucun parse asynchrone requis), avec `total_rows` / `processed_rows` / `staging_count` renseignés. Le nom saisi (défaut = nom du fichier) est stocké dans `options.import_name`. Le chemin du fichier uploadé est conservé dans `input_file_path` (téléchargeable via l'action « Fichier source »).
- Insère les `StagingRecord` (`status=Pending`) puis redirige vers `ViewImportJob`. La grille « Colonnes à traiter » du formulaire Create reste masquée quand `config_id` est absent (déjà géré par `visible()`).
- Couverture : `tests/Feature/AiImporter/PreparedCsvImporterTest` (insertion staging, BOM + défaut nom = fichier, fichier en-têtes seules → 0 staging).

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
  - **Logs granulaires parse** : après chaque ligne parsée avec succès, un `ImportLog` niveau Info est produit au format `Ligne {n}/{total} : [{reference}] {name}`. Insérés en **batch** (`DB::table()->insert()`) toutes les `checkpoint_every` lignes **ou** dès que `defaults.log_flush_seconds` (2 s) est écoulé depuis la dernière écriture, au premier des deux (et au flush final). Décision 2026-10 : le seul seuil de 100 lignes laissait la console vide ~10 min sur un parse avec actions IA (~6 s/ligne) ; le seuil temporel garde un INSERT par paquet sur un parse rapide. La progression (`processed_rows`) suit le même rythme. À chaque écriture, le statut est relu : un job passé `cancelled` (bouton « Annuler ») s'arrête proprement avec un log « Préparation annulée ».
  - **Catégories live** (`Services/CollectionCatalog`) : plus de liste de catégories figée dans le contexte IA. Le marqueur `{{categories}}` de `ai.global_context` est remplacé, à chaque appel IA, par les chemins réels de l'arbre `lunar_collections` (`Accueil>Parent>Enfant`, un par ligne), lus en base une fois par job (`flushConfigCache()` / début d'import). Sans marqueur, le contexte est inchangé (anciennes configs inaffectées). Inverse côté writer : `resolveCollectionIds()` accepte un id, un slug d'URL (les `lunar_collections` n'ont **pas** de colonne `handle`, l'ancien `where('handle')` faisait planter toute ligne avec une catégorie non numérique) ou un chemin `A>B>C` résolu nom par nom (insensible à la casse/accents, racine « Accueil » ignorée). Catégorie introuvable → warning, jamais d'erreur de ligne.
  - **Caractéristiques live** (`Services/FeatureCatalog`) : même principe avec le marqueur `{{features}}`, remplacé par une ligne par famille (`Famille : Valeur 1, Valeur 2`, lues dans `pko_feature_families` / `pko_feature_values`). La sortie de l'IA (`Famille:Valeur 1,Valeur 2|Famille 2:Valeur`, chaîne et non tableau) est convertie en handles par `FeatureCatalog::parse()` (famille/valeur retrouvées par nom ou handle, insensible casse/accents) avant `Features::syncByHandles()`. Avant 2026-10 le writer ignorait silencieusement toute valeur `features` qui n'était pas un tableau. Famille ou valeur inconnue → warning `feature(s) ignoré(s)`, pas d'erreur. Coût : avec ~500 catégories et 19 familles, le contexte rendu fait ~37 000 caractères ; garder `context_cache` activé.
  - **Phase des logs** : colonne `pko_ai_importer_logs.phase` (`LogPhase` : `parse` | `import`). Parse → `parse` ; `ImportStagingToLunarJob`, `LunarProductWriter`, `LunarBackupManager` → `import`. Les logs antérieurs à la colonne (NULL) sont rangés côté préparation (`ViewImportJob::phaseLogs()`). `created_at` est toujours posé par l'application (`now()`, `ImportLog::row()` pour les inserts en bloc) : le défaut SQL `CURRENT_TIMESTAMP` est en UTC alors que l'app est en `Europe/Paris`, d'où un décalage de 2 h dans la console.
- **Dépendance ajoutée** : `phpoffice/phpspreadsheet: ^3.0` au composer racine (PhpSpreadsheet 3.x — API stable, PHP 8.2+).

#### Phase 4 — Écriture Lunar + backup/rollback

- **`Services/LunarProductWriter`** : écriture d'un `StagingRecord` vers `Product` + `ProductVariant` + `Price` + `Collection` + `Brand` + custom Features. Résolution par SKU (`reference`). Cache par instance pour brand et collection lookups. Contrat de clés staging documenté in-code — voir la docblock de la classe pour la liste exhaustive (17 clés reconnues).
- **`attribute_data`** assemblé comme Collection de `Lunar\FieldTypes\TranslatedText` pour `name`, `description`, `description_short`, `meta_title`, `meta_description`, `meta_keywords` + `Text` pour `url_key`. Préserve les traductions existantes sur update (merge par langue).
- **Prix** : `Price::updateOrCreate` keyé sur `(priceable_type, priceable_id, currency_id, customer_group_id=null, min_quantity=1)`, valeur en cents entiers. Support `compare_price_cents`.
- **Features catalogue** : `Pko\CatalogFeatures\Facades\Features::syncByHandles()` via `class_exists` guard (le writer reste utilisable même si catalog-features est désinstallé).
- **`Services/LunarBackupManager`** : snapshot avant import. Collecte les SKUs présents en staging → récupère les `ProductVariant` correspondants + leurs produits + prix + pivot `lunar_collection_product`. Stockage `storage/app/ai-importer/backups/job_{uuid}_{datetime}.json.gz` (gzip level 6, typiquement 70-80% compression). Format JSON brut, lisible à l'œil. `restore()` replay en transaction. **Format v2 et règles de restauration : §7.quinquies.16.4.**
- **Logs granulaires import** : `ImportStagingToLunarJob` écrit une ligne Info par produit traité (`Ligne {n}/{total} : [{reference}] {name} — Créé|Mis à jour|Ignoré|Erreur : …`), aussitôt. Une ligne que le writer rejette sans exception (référence manquante, nom absent à la création → staging `error`) compte dans `error_count`, plus dans `imported_count`. `LunarProductWriter` émet des `ImportLog` niveau Debug à chaque étape par ligne (`Début importRow`, `Recherche produit existant (joinColumn: X)`, `valeur clé = …`, `Produit trouvé id=…` / `Création produit`, `Mode update: …`, et logs image `Image téléchargée` / `Image déjà existante, ignorée`). Activé via `$writer->setJobId($jobId)` (appelé depuis `ImportStagingToLunarJob::handle()`). Les logs sont accumulés dans un buffer interne (`pendingLogs[]`) et **flushés en batch** une fois par `write()` dans un `finally` (1 INSERT par ligne, quel que soit le nombre d'étapes).
- **`Jobs/ImportStagingToLunarJob`** (réel) : boucle `chunkById(200)` sur staging (statuts `pending|validated|warning`), appelle le writer par row, gère les 3 politiques d'erreur :
  - `ignore` → log + continue
  - `stop` → retourne false depuis `chunkById`, transition `import_status=error`
  - `rollback` → `LunarBackupManager::restore()` + `import_status=rolled_back`
- Snapshot créé UNE fois au premier run. Sur resume, le backup_path existant est réutilisé.

#### Phase 5 — UI Filament (RelationManagers + Actions)

- **`StagingRecordsRelationManager`** (refonte UX 2026-06) : tableau à **colonnes dynamiques** générées depuis les clés réelles du staging data (union du premier StagingRecord), labellisées via `ProductFieldCatalog::flat()`. Fonctionne pour les jobs avec ET sans `ImporterConfig` (ex : import CSV préparé). Si un config est présent, ses clés de mapping servent à ordonner les colonnes. Miniature image si `data.image`/`data.images` présent (`ImageColumn`). Statut : badge coloré (`TextColumn->badge()->color()`) + `SelectColumn` adjacent pour l'édition inline. **Double-clic hybride** (décision architecturale) : chaque cellule de données est une `TextColumn->html()` rendant un composant Alpine.js — double-clic → input inline → `$wire.updateCellValue(id, key, value)` → persist sur `StagingRecord->data[key]` (scoped au job courant). Modal d'édition : formulaire **champ par champ** (un `TextInput`/`Textarea` par clé de mapping, labellisé FR) avec layout 2 colonnes (logs à gauche, champs à droite). `mutateFormDataUsing` merge les clés éditées dans le blob `data` existant pour ne pas perdre les clés hors formulaire. Bulk actions : valider / ignorer / supprimer. Export CSV conservé.
- **`ImportLogsRelationManager`** : tableau read-only, polling 5s, tri `id desc`, filtres par phase et par niveau, badge coloré. Fournit la vue temps-réel pendant le parse/import.
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
| `cost_price_cents` | `ProductVariant::pko_cost_price` (cents) | Prix d'achat / coût. **Assignation directe** (colonne custom non-fillable → un mass-assignment la droppe silencieusement). Sert au calcul de marge. |
| `weight_value` | `ProductVariant::weight_value` (kg) | Unité forcée à `kg` |
| `length_value` / `width_value` / `height_value` | `ProductVariant::{axis}_value` (cm) | Unité forcée à `cm` |
| `brand_name` | `Product::brand_id` | `Brand::firstOrCreate(['name' => ...])` |
| `collections` | `Product::collections()` | Array ou CSV, int (ID) ou string (handle). `syncWithoutDetaching` |
| `features` | pivot `pko_feature_value_product` | Hash `{family_handle => [value_handle, ...]}`, delegated à `catalog-features` |
| `images` | Spatie MediaLibrary | Array ou CSV d'URLs distantes. Idempotent via `custom_properties.source_url`, première URL `primary=true`. |
| `videos` | `pko/product-videos` | Array/CSV d'URLs **ou** JSON `[{url,title}]` (format prépa PrestaShop) YouTube/Vimeo/Dailymotion/MP4. Titre conservé. Idempotent par URL. Voir §7.quinquies.15octies. |
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
| `wholesale_price` | `cost_price_cents` | **×100 puis `(int) round()`** (prix d'achat euros → cents) |

Les casts ci-dessus passent par `Support\NumberParser` : vide → clé ignorée (jamais 0),
illisible → clé ignorée + warning. Détail en §7.quinquies.16.1.

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
[SKU-12345] introuvable(s) sur le site, ignoré(s) — 2 collection(s) : Accueil>A>B ; Accueil>C — 3 feature(s) : family:X ; value:Famille.Y ; value:Famille.Z
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

`ImporterConfigResource` reproduit l'éditeur du module Publiko AI Importer en thème Filament/Lunar. Onglet **Éditeur visuel** (3 sections) + onglet **JSON brut** (échappatoire). Les deux pilotent la même colonne `config_data`, mais via **deux chemins d'état Livewire distincts**.

> **⚠️ Gotcha — collision de chemin d'état (corrigé 2026-06).** L'onglet JSON brut ne doit **jamais** être un champ nommé `config_data` : ce chemin est déjà la racine des champs imbriqués `config_data.*` de l'éditeur visuel. Les deux fields entrent alors en collision sur `$data['config_data']` (le textarea reçoit un tableau → JS affiche `[object Object]`, et les repeaters lisent une string → mapping vide). Symptôme constaté sur une config SOMFY importée. **Fix** : le textarea JSON vit sur une clé scratch dédiée `config_json` (`->dehydrated(false)`), jamais persistée telle quelle — `config_data` reste produit par `dehydrateVisual`.
>
> **Sync bidirectionnelle** : `config_json` est `->live(onBlur: true)` ; en quittant le champ, son `afterStateUpdated` parse le JSON (silencieux si invalide) puis `$set('config_data', hydrateVisual(...))` → l'éditeur visuel se met à jour. Sens inverse : bouton **« Régénérer depuis l'éditeur visuel »** (`Forms\Components\Actions`) qui `dehydrateVisual` l'état courant → `$set('config_json', ...)`. `hydrateVisual()` initialise le miroir `config_json` au fill.

**Sections de l'éditeur visuel :**
1. **Feuilles Excel** — Select **Type de source** (`FAB-DIS` / `CSV` / `XLSX`, mappé sur `config_data.type` — repris par `saved-configs.blade.php` pour le badge Type), puis `primary_sheet` + `join_key` globale, puis Repeater « Feuilles avec relations » (nom, relation `one`/`many`, colonne de jointure, type, toggle en-têtes). Mappe sur `config_data.sheets{}`.
2. **Configuration IA (optionnel)** — toggle `ai.context_cache` + textarea `ai.global_context`. Le bloc `ai` est **omis du JSON** si inutilisé (cache off + contexte vide), via `normalizeAi()`.
3. **Mapping des colonnes — blueprint dense (refonte 2026-06).** Reproduction fidèle de la table « blueprint » du module PS : **une ligne resserrée par colonne cible**, structurée `[Select colonne cible | résumé CONFIGURATION riche]` + actions d'item (Configurer / dupliquer / supprimer / réordonner). Le Select « colonne cible » utilise des `options()` dynamiques : si la clé courante est **hors catalogue Lunar** (clé PS pure : `id`, `unity`, `id_tax_rules_group`…), elle est ajoutée à un groupe « Autres (source d'origine) » avec son **libellé ami** (`ProductFieldCatalog::LEGACY_LABELS`, ex. `price_tex` → « Prix HT ») → la valeur reste sélectionnée et **n'est jamais perdue à la sauvegarde**. Le mapping clé legacy → clé canonique Lunar reste assuré au **write** par `LunarProductWriter::normalizeLegacyKeys()`, pas dans l'éditeur.
   - **Résumé CONFIGURATION** : rendu serveur par `Pko\AiImporter\Support\PipelineSummary::render($row)` (testable, `tests/Unit/AiImporter/PipelineSummaryTest`) — badge feuille + colonne source + `déf:`, puis le pipeline en chaîne fléchée, le `condition` étant **déplié en blocs colorés SI / ALORS / SINON SI / SINON** (jaune/vert/rouge) ; chaque action en chip avec libellé FR + indice de paramètre (`Multiplier ×1.2`, `Diviser ÷10`, `Préfixe « SOM »`, `Concaténer 5 sources`, `Prompt IA`…). Tout texte dynamique est `e()`-échappé.
   - **`col` / `sheet` / `default` sont édités dans le modal Configurer** (comme l'écran PS), pas en inline dans la table : ce ne sont pas des composants du repeater de mapping. La lecture/écriture passe par `getRawItemState()` / `getState()` (le raw state conserve ces clés non-composant).
   - **Toolbar blueprint** (`resources/views/filament/mapping-toolbar.blade.php`, `Forms\Components\View`) : recherche + « masquer les colonnes vides », **filtrage 100 % client-side Alpine** sur les `.fi-fo-repeater-item` du repeater frère (`[data-mapping-repeater]`), via `MutationObserver` pour survivre aux morphs Livewire. Dégradation gracieuse : aucun repeater trouvé → no-op, formulaire intact.

> **⚠️ Gotcha — modal « Configurer » systématiquement vide (corrigé 2026-06).** `fillForm`/`modalHeading`/`modalContent` doivent lire l'état via **`getRawItemState($uuid)`**, jamais `getItemState($uuid)`. Ce dernier reconstruit l'état depuis les **composants enfants** du repeater de mapping ; or `actions`/`col`/`sheet`/`default` n'y sont pas (édités uniquement dans le modal) → ils sont écartés et le modal s'ouvre vide.

**Builder de pipeline (modal « Configurer ») — éditeur JS custom (refonte 2026-06).**

Le modal n'est **plus** un formulaire Filament : c'est l'éditeur « Pipeline d'actions » du module PrestaShop **lifté verbatim** (`resources/js/pipeline-editor.js`, ~3100 lignes, moteur vanilla `window.PkoPipelineEditor`), reproduit **trait pour trait** : panneau gauche = flux d'actions avec **connecteurs verticaux à pastilles** et blocs colorés **SI (orange `#ff9800`) / ALORS (vert `#27ae60`) / SINON SI / SINON (rouge `#e74c3c`) / PUIS (cyan `#5bc0de`)** ; panneau droit = **palette catégorisée** (Logique / Mathématique / Texte / Rechercher-Remplacer / Combiner / Correspondance / Dates-Validation / IA / Agrégation) ; champs source FEUILLE / COL. / PAR DÉFAUT en tête ; zones de dépôt « Cliquez ici puis ajoutez une action » ; drag-to-reorder ; bouton « Tout effacer ».

Pourquoi un lift plutôt qu'un rebuild Filament : trois tentatives de reproduction « à la Filament » avaient échoué sur la fidélité visuelle ; le moteur PS produit déjà **exactement** l'UI voulue et l'utilisateur a explicitement autorisé une vue 100 % custom. Le moteur est **dependency-light** (jQuery uniquement pour les modals Bootstrap, remplacés) ; on neutralise son `init()` full-page et on n'expose que le sous-système modal via `PkoPipelineEditor.mount({root, seed, onChange})` / `.collect()` / `.clear()`.

Intégration (sans fragilité d'entangle) :
- **Assets injectés au niveau page** (`AiImporterServiceProvider` → `FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, …, scopes: [Create/Edit])`, vue `pipeline-editor-assets.blade.php`) : `<style>` + moteur JS + globals (`window.actionTypes/actionGroups/conditionOperators/llmConfigs/prestashopColumns/pkoaiTranslations`). Indispensable hors composant Livewire — `PAGE_END` casserait sur `MultipleRootElementsDetectedException`, et les `<script>` d'un contenu de modal chargé par Livewire ne s'exécutent pas de façon fiable.
- **Contenu du modal** (`pipeline-editor-modal.blade.php`, markup PS fidèle) sous `wire:ignore` (protège le DOM construit par le moteur du morphing Livewire) ; montage via Alpine `x-init`.
- **Pont d'état** : l'action porte un seul champ `Hidden::make('pipeline_json')` (`data-pko-pipeline-json`). Le moteur resynchronise l'état canonique `{sheet, col, default, actions}` dans ce champ à chaque modification (set value + dispatch `input` → `wire:model` deferred) ; au submit « Appliquer », `->action()` le décode et le replie dans le raw state de la ligne.

**Palette (`Pko\AiImporter\Support\PipelineEditorManifest`).** Reproduction fidèle de `getActionTypes()` du module PS (types, icônes, groupes, schémas de params, labels FR). Émet les types **legacy séparés** (`multiply`/`divide`/`add`/`subtract`/`uppercase`/`lowercase`/`capitalize`) : tous **exécutables** côté Lunar (`ActionRegistry` enregistre les alias, `MathAction`/`ChangeCaseAction::fromArray()` les normalisent) → l'éditeur sérialise directement vers le `config_data` canonique consommé par `ActionPipeline`, **sans couche de traduction**.

**Round-trip canonique.** Les `actions` par colonne restent dans leur forme canonique `{type, …params}` / `{type, branches, else_actions}` de bout en bout : `hydrateVisual`/`dehydrateVisual` n'appliquent **aucune** expansion form-shape sur les actions (l'éditeur lit/écrit exactement ce que `ActionPipeline` exécute). Les clés scratch `sheets_repeater`/`mapping_repeater` ne servent que pour les feuilles et l'enveloppe de ligne.

**Classes support & garde-fous :** `ProductFieldCatalog` (champs Lunar + `LEGACY_LABELS`), `PipelineEditorManifest` (palette de l'éditeur JS), `PipelineSummary` (résumé serveur de ligne, dépliage `condition`). Tests : `PipelineEditorTest` (chaque type de palette exécutable + round-trip `config → éditeur → config` sur un pipeline représentatif), `ImporterConfigRoundTripTest`, `PipelineSummaryTest`, `EditImporterConfigTest` (rendu page Edit). Branding neutre (aucun nom client ; le préfixe `pko` reste autorisé dans le code).

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
>
> **Anti double-dispatch** : la commande ne matche que les jobs `parsed` avec
> `import_status ∈ {Pending, Scheduled}` et `scheduled_at <= now`. Au dispatch elle
> transitionne le job en `import_status=queued` (`ImportStatus::Queued`, label « En
> file », color info) **avant** d'envoyer `ImportStagingToLunarJob`. Comme `queued`
> est hors du filtre d'éligibilité, un tick cron ultérieur ne re-matche plus le job
> même si le worker queue prend du retard (le job reste `status=parsed` tant que le
> worker n'a pas démarré) → plus de double import des produits. Le worker repasse
> ensuite à `importing`. Les actions UI `launchImport`/`resumeImport` passent elles
> aussi à `queued` au dispatch (même garde anti double-clic).

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
  - *Logs de console* — deux onglets **Préparation** / **Import** (badge = nombre
    de logs, onglet Import ouvert d'office dès qu'un import a démarré), chacun une
    vue terminal `pko-ai-importer::filament.infolists.console-logs` filtrée par
    `phase` (300 derniers `ImportLog`, niveaux colorisés), `wire:poll.3s` tant que
    la phase est en cours. **Complète** `ImportLogsRelationManager` (conservé pour le filtrage).
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

### 7.quinquies.15bis Flow piloté CRON — 2 phases découplées (2026-07)

Refonte du déclenchement pour que **les deux phases longues soient attrapées par le
cron**, jamais exécutées en synchrone dans la requête web. Motivation : sur DEV/prod
mutualisé `QUEUE_CONNECTION=sync` → un `dispatch()` dans un controller Filament
exécutait le parse **inline** (bloquant, fatal sur gros fichiers / appels LLM).

**Cycle de vie complet** :

```
création  ──▶  status=pending (« En attente du CRON »)
              [cron: phase parse]  ParseFileToStagingJob
          ──▶  status=parsing  ──▶  status=parsed (staging prêt à vérifier)
              [admin: vérifie / corrige / valide le staging]
              [action « Programmer l'import Lunar »]  import_status=scheduled + scheduled_at=now
              [cron: phase import]  ImportStagingToLunarJob
          ──▶  import_status=queued ──▶ importing ──▶ imported
```

**`RunScheduledImportsCommand` (`ai-importer:run-scheduled`) — 2 passes** :
1. **Parse** : jobs `status=pending` **avec `config_id`** (les CSV pré-préparés naissent
   déjà `parsed`, donc exclus). Aucun `scheduled_at` requis → la préparation démarre au
   plus tôt. Transition `status=parsing` **avant** dispatch (sortie de l'état éligible →
   pas de re-parse au tick suivant).
2. **Import** : jobs `status=parsed`, `import_status ∈ {pending, scheduled}`,
   `scheduled_at` non nul et `<= now`. Transition `import_status=queued` avant dispatch
   (garde anti double-import inchangée).

`--dry` (bouton « Tester CRON ») liste les deux phases sans rien dispatcher.
Planifié `everyTwoMinutes()` + `withoutOverlapping(10)` + `runInBackground()`
(`routes/console.php`).

**Points de câblage modifiés** :
- `CreateImportJob::afterCreate()` : **ne dispatche plus** le parse — le job reste
  `pending`, une notification indique que le CRON le prendra. (Auparavant : dispatch
  immédiat = parse synchrone au clic.)
- `ViewImportJob` : `launchImport` (relabellé « Programmer l'import Lunar ») et
  `resumeImport` **ne dispatchent plus** `ImportStagingToLunarJob` ; ils marquent
  `import_status=scheduled` + `scheduled_at` (respecte un `scheduled_at` déjà posé au
  create, sinon `now()`). C'est le cron qui déclenche l'écriture Lunar. Corrige au
  passage un bug propre au driver `sync` : `dispatch()` suivi de
  `update(import_status=queued)` écrasait le statut final (`imported`) par `queued`
  après le run inline.
- `launchImport` visible uniquement si `import_status=pending` (masqué une fois
  programmé). Le bouton « Exécuter CRON » (page Create) reste le déclencheur **manuel**
  immédiat des deux phases (utile en test), équivalent d'un tick anticipé.

> **Latence assumée** : après « Préparer les données », la préparation démarre au
> prochain tick cron (≤ 2 min). C'est voulu : le parse peut durer des heures (LLM par
> ligne) et ne doit jamais tourner dans la requête web.

### 7.quinquies.15ter Fix affichage — logs & tableau staging (2026-07)

- **Logs de console** (`console-logs.blade.php`) : le conteneur portait
  `white-space:pre-wrap`, qui rendait **littéralement les retours-ligne + l'indentation
  du source Blade** entre chaque `<span>` → lignes empilées et décalées. Corrigé :
  chaque entrée est une ligne `display:flex` resserrée (timestamp / niveau / #ligne en
  `flex:none`, message en `flex:1` avec `pre-wrap` **local** au message seul).
- **Tableau staging vide** (`StagingRecordsRelationManager`) : les cellules data étaient
  des `TextColumn->html()` rendant un composant Alpine (`staging-cell.blade.php`). Or
  `->html()` déclenche `Str::sanitizeHtml()` de Filament qui **strip les directives
  Alpine (`x-data`/`x-text`/`@dblclick`) et les `<input>`** → chaque cellule se rendait
  **vide** (le double-clic inline n'a jamais fonctionné en navigateur). Corrigé :
  colonnes `TextColumn` **texte simple** (échappé), `limit(40)` + tooltip valeur
  complète + `toggleable()`. L'édition d'une valeur passe désormais par le **modal
  d'édition de ligne** (EditAction, champ par champ — déjà fonctionnel). Composant
  Alpine `staging-cell.blade.php` + méthode Livewire `updateCellValue` supprimés (morts).

### 7.quinquies.15quater Fix parse — héritage source de `multiline_aggregate` + colonne Photo (2026-07)

- **`multiline_aggregate` — héritage col/sheet/type_col (`ActionPipeline::inheritColumnSource`)** :
  les configs PrestaShop réelles (ex. `image` de somfy.json) déclarent la **feuille**
  (`sheet: B03_MEDIA`) et la **colonne** (`col: N`) au niveau de la COLONNE, et le
  `type_col` (`MTYP`) au niveau de la FEUILLE — **pas dans l'action**, qui porte
  `columns: []`. Sans héritage, l'action tournait avec `sheet=''` / `columns=[]` /
  `type_col='type'` → agrégation **vide** (symptôme : colonne image toujours vide en
  staging). `ActionPipeline` injecte désormais, pour les seules actions
  `multiline_aggregate` qui ne les restatent pas : `sheet` ← `col.sheet`,
  `columns` ← `[col.col]`, `type_col` ← `config_data.sheets[sheet].type_col`. Les
  actions qui fixent explicitement ces clés ne sont pas écrasées. Ce chemin n'était
  **pas** couvert (le test e2e exerçait `concat` multi-sources, pas
  `multiline_aggregate`) → verrouillé par `tests/Feature/AiImporter/MultilineAggregateInheritanceTest`.
- **Colonne « Photo » du staging (`StagingRecordsRelationManager::extractImageUrl`)** :
  affiche la **première** URL disponible. La valeur `image` produite par
  `multiline_aggregate concat` est une **CSV d'URLs** (`url1,url2`) — `extractImageUrl`
  gère URL simple / CSV / array / JSON encodé et renvoie la 1ʳᵉ URL non vide (au lieu
  de la chaîne CSV entière, qui cassait le `<img>`).

### 7.quinquies.15quinquies Images & documents → médiathèque custom (2026-07)

Refonte de l'écriture des médias par `LunarProductWriter` pour passer par la
**médiathèque custom** (média-core) au lieu de l'API Spatie native de Lunar.

**Problème corrigé** : l'ancien `ProductImagePipeline` écrivait les images en média
Spatie *possédé par le produit* (collection `images`). Or l'app lit les images produit
via **`pko_mediables`** (média-core, groupe `product`) dans l'éditeur unifié
(`MediaPicker->mediagroup('product')`) → images **invisibles en admin**. Le storefront
lisait `$product->media` (Spatie) → visibles seulement là. Incohérence.

**Nouveau flux** (dépend de `pko/lunar-media-core` + `pko/lunar-product-documents`) :
- **Images** (`images`) → `MediaLibraryImporter::importFromUrl($url, 'products')` (dédup
  source_url + sha1, média possédé par le `Folder` `products`) → lien
  **`pko_mediables`** (`mediable_type=Product::class`, `mediagroup='product'`, append
  idempotent). Visibles admin **et** storefront.
- **Documents** (`documents`, alias legacy `attachments`) → import en collection
  `documents` (PDF autorisé) → `ProductDocumentManager::attach()` avec **catégorie**
  déduite du type (`NOTICE`/`BROCH` → `DocumentCategory::firstOrCreate(handle)`).
  Le staging produit la valeur via `multiline_aggregate method=json_array`
  (`[{type,url,name}]`) ; `LunarProductWriter::decodeDocuments()` la parse.
- Nouveau champ **`documents`** dans `ProductFieldCatalog` (sélectionnable dans
  « colonnes à importer »). Alias `attachments → documents` dans `normalizeLegacyKeys`.

**Storefront** : `App\Livewire\ProductPage::getImagesProperty()` migré pour lire
`pko_mediables` (groupe `product`), avec fallback sur `$product->media` (Spatie) pour
les galeries non migrées. Cohérent avec l'admin.

`ProductImagePipeline` (écriture Spatie directe) n'est plus utilisé par le writer
(classe conservée, dépréciée). Tests : `MultilineAggregateInheritanceTest`,
`AttachmentsAliasTest` (le download réseau réel n'est pas testé en unit).

> **⚠️ Gotcha — prix « fantôme » (morph alias, corrigé 2026-07).** `upsertPrice`
> écrivait le prix via `Price::updateOrCreate(['priceable_type' => ProductVariant::class, …])`
> (FQCN). Or Lunar mappe `ProductVariant` sur l'alias morph **`product_variant`** et la
> relation `$variant->prices` filtre par cet alias → le prix FQCN était **présent en base
> mais introuvable par l'app** (Tarification vide dans l'éditeur produit, prix absent au
> storefront). **Fix** : créer le prix **via la relation** `$variant->prices()->updateOrCreate(…)`
> qui pose l'alias correct. Data-fix des lignes déjà importées :
> `UPDATE lunar_prices SET priceable_type='product_variant' WHERE priceable_type='Lunar\\Models\\ProductVariant'`.
> (Le lien `pko_mediables` utilise volontairement le FQCN `$product::class` — cohérent
> des deux côtés avec `MediaPicker`, donc pas concerné.)

### 7.quinquies.15sexies Couverture champs étendue (2026-07)

Ajout au writer des champs source qui avaient une cible réelle :

| Clé source | Cible | Note |
|---|---|---|
| `tags` (CSV) | `Product::syncTags` | Lunar met les valeurs en **MAJUSCULES**. |
| `mpn` | `ProductVariant.mpn` | — |
| `minimal_quantity` → `min_quantity` | `ProductVariant.min_quantity` | alias legacy ajouté ; défaut 1. |
| `supplier` (nom) | `Product.pko_supplier_id` | `Supplier::firstOrCreate`. **Assignation directe** (colonne non-fillable → un mass-assignment la droppe silencieusement). |
| `image_alt` | `custom_properties.alt` du média | posé si absent (pas d'écrasement sur média dédupliqué partagé). |
| `wholesale_price` → `cost_price_cents` | `ProductVariant.pko_cost_price` (cents) | Prix d'achat (coût). Alias legacy `wholesale_price` (euros ×100). **Assignation directe** (colonne non-fillable). Voir §7.quinquies.15septies. |

Tous ajoutés à `ProductFieldCatalog` (sélectionnables dans « colonnes à importer »).

**Non couverts** (pas de colonne cible sans migration) : `ecotax`,
`supplier_reference` (pas de champ réf-fournisseur natif Lunar), promos
(`on_sale`/`reduction_*`/prix barré sans source), `visibility`, `condition`,
`unit_price`, `delivery_*`, `available_*`, `additional_shipping_cost`. À décider au
cas par cas (nécessiteraient une colonne dédiée).

### 7.quinquies.15septies Prix d'achat (coût) & marge (2026-07)

Le prix d'achat PrestaShop (`wholesale_price`, en euros) est désormais **importé** et
sert de base au calcul de marge dans l'éditeur produit.

- **Colonne** : `lunar_product_variants.pko_cost_price` (`unsignedBigInteger` nullable,
  **cents entiers**), ajoutée via migration custom `2026_07_14_000001_add_pko_cost_price_to_product_variants.php`
  (`Schema::table()`, jamais toucher la migration Lunar).
- **Import** : clé canonique `cost_price_cents` + alias legacy `wholesale_price` (euros
  ×100 arrondi, même mécanisme que `price_tex → price_cents`). Écrite par assignation
  directe (`applyCostPrice()`) car la colonne `pko_` n'est **pas fillable** (comme
  `pko_supplier_id`). Écrite à la création, en update `all`, et en update `price`.
  À sélectionner dans « colonnes à importer » (sinon filtrée comme toute clé non cochée).
- **Éditeur** (`EditProductUnified`) : champ « Prix d'achat (coût) » désormais chargé au
  mount (`pko_cost_price` → affichage euros) et sauvé dans `persistPrices()` (assignation
  directe). La **marge (HT)** est affichée en lecture seule sous les champs prix :
  montant `= prix HT − coût` et pourcentage `= (prix − coût) / prix × 100` (computed
  Livewire `getMarginProperty`, recalculée au blur). Les prix sont **HT**
  (`lunar.pricing.stored_inclusive_of_tax=false`) → le coût est HT, la marge est HT.

### 7.quinquies.15octies Vidéos au format JSON `[{url,title}]` (2026-07)

La prépa PrestaShop (`multiline_aggregate` → `json_array`) sérialise la colonne
`videos` en **string JSON** `[{"url":...,"title":...}]`, pas en CSV d'URLs.

**Bug corrigé** : l'ancien writer faisait `explode(',', $data['videos'])` sur cette
string → découpait le JSON sur chaque virgule (URL + titre) → 0 vidéo importée,
titres perdus. Désormais un `decodeVideos()` (analogue à `decodeDocuments()`) parse
le format objet et attache via `ProductVideoManager::addIfNotExists($product, $url,
$title)` — **titre conservé**, idempotent par URL, URL non reconnue loggée en warning.
Formats acceptés : JSON `[{url,title}]`, JSON `["url", ...]`, CSV d'URLs, array natif.

### 7.quinquies.15 Compatibilité PrestaShop réelle — périmètre & non-régression

Les configs JSON du module PrestaShop *Publiko AI Importer* tournent **directement**,
sans réécriture du mapping, dans les limites décrites ici. Cette compat est désormais
**verrouillée par des tests qui chargent les VRAIES configs** (`config/somfy.json`,
`config/example-avec-actions.json`, `config/test-new-actions.json` du module PS,
copiées en fixtures read-only dans `tests/Fixtures/ai-importer/`).

#### ✅ Supporté tel quel

| Élément PS | Mécanisme |
|---|---|
| `action: {}` (objet singulier) | Lifté en `actions: [ {} ]` à l'import (`ImportPsConfigCommand::normalise`) |
| `comment` / clés `_*` dans une action | Ignorées silencieusement (`Action::filterConstructorParams`) |
| `comment` au niveau colonne | Non lu par le pipeline (toléré) |
| Alias `multiply`/`divide`/`add`/`subtract` | → `MathAction` |
| Alias `uppercase`/`lowercase`/`capitalize` | → `ChangeCaseAction` (mode dérivé) **réellement enregistrés** |
| `prefix` au niveau colonne (`{"col":"REFCIALE","prefix":"FAA"}`) | Concat avant pipeline (`ActionPipeline::applyAffixes`) |
| `category_map` (`values` + `default_category`) | → CSV de handles |
| `conditional` (`{condition:"> 0", if_true, if_false}`) | Ternaire scalaire |
| `concat` / `template` avec `sources: {col, sheet}` | Résolution multi-feuilles (`ResolvesSheetSources`) |
| Feuilles jointes (`sheets` + `join_key` / `join_col`, `type_col: MTYP`) | Index secondaire + `multiline_aggregate` |
| Mapping en lettres de colonne (`col: "M"`) | Double-clé header **ET** lettre (`SpreadsheetParser`) |
| Clés writer legacy FAB-DIS (`ean13`, `quantity`, `manufacturer`…) | Normalisées (§7.quinquies.9) |

#### ⚠️ `llm_transform` — coûts API réels

`llm_transform` n'est **PAS** un no-op en production : sur une config avec un
`LlmConfig` **actif**, chaque ligne parsée déclenche un **vrai appel API
facturable** (Anthropic / OpenAI). En dev/test la table `pko_llm_configs` est vide →
`LlmConfig::default()` renvoie `null` → l'action retourne la valeur source sans
toucher au réseau (no-op sûr).

> **NE JAMAIS lancer une préparation de fichier (parse réel) sur une config
> contenant des actions `llm_transform` sans budget API maîtrisé et sans
> `LlmConfig` choisi en connaissance de cause.** `config/somfy.json` contient une
> telle action — son **import** (`ai-importer:import-ps-config`) reste sûr (simple
> insertion DB, zéro appel), mais son **parse réel** contre une config LLM active
> coûte de l'argent à chaque ligne.

#### Couverture non-régression (tests dédiés)

- **`tests/Feature/AiImporter/ImportPsConfigCommandTest`** : importe les 3 vraies
  configs PS (exit 0, mapping intégral conservé), vérifie la normalisation
  `action`→`actions`, et garantit `Http::assertNothingSent()` (zéro coût à l'import).
- **`tests/Feature/AiImporter/PsConfigParseE2eTest`** : parse e2e d'un classeur XLSX
  3 feuilles (B01_COMMERCE primaire + B02_LOGISTIQUE/B03_MEDIA jointes) via
  `ParseFileToStagingJob`, asserte le résultat staging de chaque action PS critique
  (prefix `REFCIALE`→`FAA…`, `uppercase`/`lowercase`/`capitalize`, `multiply`+`comment`,
  `category_map`, `conditional > 0`, `concat`/`template` multi-feuilles, `replace`).
  Une colonne `llm_transform` y reste volontairement présente pour prouver le no-op,
  sous `Http::fake()` + `assertNothingSent()` + aucun `LlmConfig` actif.

### 7.quinquies.16 Correctifs critiques (audit 2026-10-01)

#### 7.quinquies.16.1 Valeurs numériques vides ou décimales FR

**Problème** : `price_tex` vide ou `"12,50"` donnait `price_cents = 0`, upserté → le prix
existant était écrasé à 0 (update_mode `all`/`price`). Même chose pour `stock` (`''` → 0),
`min_quantity`, dimensions, `cost_price_cents`.

**Décision** : un helper unique `Pko\AiImporter\Support\NumberParser` parse tout champ
numérique du writer (`LunarProductWriter::NUMERIC_FIELDS` + `price_tex`/`wholesale_price`) :

| Valeur source | Effet |
|---|---|
| `null`, `''`, espaces | clé **retirée** : rien n'est écrit, la valeur en base est conservée |
| `12,50`, `1 234,50`, `1.234,50`, `1,234.50`, `12.50 €` | parsée (virgule décimale FR, espaces/insécables de milliers) |
| non parsable (`sur devis`, `N/C`, `1,2,3`) | clé retirée + `ImportLog` **warning** « Valeur(s) numérique(s) illisible(s) » ; la ligne reste importée |

Le prix barré suit la même règle : `compare_price_cents` vide ou absent → le prix barré
existant est conservé lors de la mise à jour du prix de base.

À la **création** d'un produit, un stock absent vaut toujours 0 et `min_quantity` 1 (défauts
Lunar) ; seul l'update préserve l'existant. Tests : `LunarProductWriterNormalizationTest`,
`WriterOptionsTest` (`test_blank_*`, `test_french_decimal_price_is_parsed`).


#### 7.quinquies.16.2 Parser : formules, texte riche, mode streamé

**Problèmes** : `readRow()` lisait `getValue()` → une formule arrivait en staging sous la
forme `"=C2*1.2"` ; une cellule texte riche (objet `RichText`) était stockée `{}` ; en mode
streamé (`loadStreamed()`, XLSX/XLS > 5 Mo) le classeur initial n'était chargé qu'avec la
ligne 1 de chaque feuille → `countRows()` = 0 et **feuilles secondaires vides** (aucune
jointure) ; la « sonde » d'`iterateStreamed()` rechargeait tout le classeur.

**Décisions** (`Services/SpreadsheetParser.php`, `Services/ChunkReadFilter.php`) :

- **Valeur de cellule** (`cellValue()`) : `RichText` → `getPlainText()` ; formule →
  `getCalculatedValue()`, repli sur la valeur en cache écrite par Excel
  (`getOldCalculatedValue()`) si le calcul lève ou renvoie une erreur Excel (`#REF!`…).
  En mode streamé seule une fenêtre de lignes est chargée : une formule visant une autre
  ligne serait calculée contre des cellules vides, donc **la valeur en cache est
  prioritaire** quand elle existe. Une formule ne fait jamais planter la ligne.
- **Mode streamé** : un seul chargement initial avec `ChunkReadFilter(windowSheet:
  primaire, fullSheets: feuilles de config.sheets)` → en-têtes de la primaire + feuilles
  secondaires **intégrales** (jointures identiques au mode complet). Les fenêtres
  suivantes ne chargent que la feuille primaire (`setLoadSheetsOnly`).
- **Nombre de lignes** : `listWorksheetInfo()` du reader (scan XMLReader, sans charger
  les données) ; plus de rechargement complet. Ce compte inclut les lignes vides mais
  **formatées** en fin de feuille : il sert d'estimation pendant le parse, puis
  `ParseFileToStagingJob` **recale `total_rows`** sur le nombre de lignes réellement
  parcourues quand le fichier a été lu jusqu'au bout (pas de `row_limit` atteint).
  L'itération écarte ces lignes (vides finales bufferisées puis jetées, comme
  `getHighestDataRow()` en mode complet).
- **Seuil du mode streamé** : `ai-importer.defaults.stream_threshold_bytes`
  (`AI_IMPORTER_STREAM_THRESHOLD_BYTES`, défaut 5 Mo), auparavant codé en dur.

Test : `tests/Unit/AiImporter/Services/SpreadsheetParserTest` (xlsx 2 feuilles généré :
mêmes lignes, même `countRows`, mêmes jointures dans les deux modes ; formule ; texte riche).

#### 7.quinquies.16.3 Queue : worker dédié et connexion « longue »

**Problème** : les jobs partaient sur `ai-importer-parse` / `ai-importer-import`, mais le
seul worker planifié (`routes/console.php`) n'écoutait que `default` → dès que
`QUEUE_CONNECTION ≠ sync`, imports bloqués sans erreur. De plus `retry_after` (90 s) <
timeout des jobs (3600/7200 s) → un job en cours aurait été re-réservé et exécuté en double.
La clé `ai-importer.queues.connection` n'était jamais lue.

**Décisions** :

- **Worker planifié séparé** (`routes/console.php`) : `queue:work <connexion>
  --queue=ai-importer-parse,ai-importer-import --stop-when-empty --max-time=55
  --timeout=7200 --tries=1`, chaque minute, `withoutOverlapping(130)` (verrou propre, expiré
  après 130 min > timeout du job d'import si le process meurt). Les files d'import ne
  sont **pas** ajoutées au worker `default` : un import de 2 h y bloquerait
  factures/mails/étiquettes. `--tries=1` est surchargé par `$tries` de chaque job (3 pour
  le parse, 1 pour l'import).
- **Connexions dédiées** `database-long` et `redis-long` (`config/queue.php`) : même
  stockage que leur jumelle, `retry_after` = `QUEUE_LONG_RETRY_AFTER` (défaut 7500 s, >
  7200 s). Rejeté : relever `retry_after` de `database`/`redis` — un job court planté
  mettrait alors 2 h à être retenté.
- **Choix de la connexion** : `ai-importer.queues.connection` = `AI_IMPORTER_QUEUE_CONNECTION`
  sinon dérivée de `QUEUE_CONNECTION` (`database` → `database-long`, `redis` →
  `redis-long`, autre/`sync` → `null` = connexion par défaut). Sans `QUEUE_CONNECTION`, le
  repli est `database` (donc `database-long`), **le même que `config/queue.php`** : les deux
  replis doivent rester alignés, sinon la file par défaut et les jobs d'import tournent sur
  deux connexions différentes (ancien repli `sync` → imports exécutés en ligne).
- **Routage centralisé** : file et connexion sont posées dans le **constructeur** de
  `ParseFileToStagingJob` / `ImportStagingToLunarJob` (`onConnection()` + `onQueue()`),
  donc tout point de dispatch (commande planifiée, bouton « Relancer le parse ») en hérite.

Tests : `RunScheduledImportsCommandTest` (file + connexion via `Queue::fake()`, `retry_after`
> timeout, worker dédié présent dans le planning et distinct du worker `default`).

#### 7.quinquies.16.4 Rollback fiable (`LunarBackupManager`, format v2)

**Problèmes** : le snapshot passait par `toArray()`, qui **aplatit** `attribute_data`
(`{"name":"…"}`) ; `restore()` réécrivait ce format → le cast Lunar `AsAttributeData` ne
pouvait plus lire les produits restaurés. Les prix étaient filtrés sur le FQCN alors que
Lunar stocke l'alias morph `product_variant` → aucun prix sauvegardé. Le pivot
collections était capturé mais jamais restauré, les produits créés par l'import restaient,
la jointure EAN n'était pas couverte, et dimensions / MPN / `min_quantity` / colonnes `pko_`
n'étaient pas restaurées.

**Décisions** :

- **Capture brute** : `getRawOriginal()` pour produits, variantes et prix (`attribute_data`
  reste la chaîne JSON Lunar), clé `version: 2` dans le payload. `whereIn` découpés par 1000
  (plafond de placeholders MySQL sur les gros fichiers).
- **Périmètre** : variantes trouvées par SKU (`reference`) **et** par EAN (`ean`/`ean13`
  normalisés comme dans le writer), quelle que soit `join_column`. Prix filtrés sur
  `(new ProductVariant)->getMorphClass()`.
- **Restauration** (une transaction) : colonnes produit `brand_id`, `product_type_id`,
  `status`, `attribute_data`, `pko_supplier_id`, `pko_port_mode` ; colonnes variante `sku`,
  `ean`, `mpn`, `stock`, `min_quantity`, `pko_cost_price`, `{weight,length,width,height}_{value,unit}`
  — via le builder Eloquent (`whereKey()->update()`), qui n'applique ni cast ni `$fillable` :
  le JSON brut et les colonnes `pko_` non fillables sont réécrits tels quels. Prix remis à
  l'identique, et les prix **ajoutés** par l'import aux variantes sauvegardées supprimés.
  Collections : `sync()` exact par produit sauvegardé (positions comprises ; un produit sans
  collection avant l'import repart sans collection).
- **Produits créés par l'import** (staging `created` + `lunar_product_id`) : **suppression
  définitive** (`forceDelete()` du modèle Lunar — l'observer purge variantes, prix, URLs,
  collections, tags, canaux ; les FK en cascade purgent documents, vidéos et
  caractéristiques ; les liens `pko_mediables` sont supprimés via le modèle `Mediable`).
  Rejeté : passer systématiquement en brouillon — le SKU resterait occupé et un réimport
  retrouverait le produit fantôme au lieu d'en créer un propre. **Exception** : si une ligne
  de commande ou de panier pointe sur une variante, le produit passe en `draft` (supprimer
  casserait l'historique de commande), avec `error_message` sur la ligne staging et un
  warning `ImportLog`. Les fichiers médias restent dans la médiathèque (hors transaction).
- **Staging après rollback** : `created` / `updated` / `imported` → `pending`,
  `imported_at = null`, `lunar_product_id = null` pour les produits supprimés → le job
  peut être réimporté proprement. `error` et `skipped` inchangés.
- **Ancien backup (v1)** : `attribute_data` aplati n'est **pas** réécrit (warning
  `ImportLog`), le reste est restauré.
- **Relations restaurées à l'identique** pour chaque produit sauvegardé (le writer peut
  aussi bien ajouter que **remplacer** : `syncTags` et `Features::syncByHandles` écrasent) :
  - tags (`lunar_taggables`) et caractéristiques (`pko_feature_value_product`) → `sync()` exact ;
  - liens médias (`pko_mediables`, `mediable_type` = `Product::modelClass()`, le FQCN posé
    par le writer), documents (`pko_product_documents`), vidéos (`pko_product_videos`) →
    lignes ajoutées supprimées, lignes modifiées remises, lignes disparues réinsérées avec
    leur id. Un lien vers un média **supprimé** de la médiathèque depuis la sauvegarde
    n'est pas réinséré (FK) : warning `ImportLog`.
  - Les fichiers médias importés restent dans la médiathèque : seuls les liens sont annulés.
  - Une sauvegarde v2 prise avant l'ajout de ces clés ne touche pas à ces relations
    (clé absente ≠ liste vide).
- **Réindexation Scout** : les updates du builder ne déclenchent aucun événement modèle →
  après commit, `searchable()` sur les produits restaurés. Un moteur de recherche
  indisponible ne fait pas échouer le rollback (warning `ImportLog`). Les produits supprimés
  sortent de l'index via l'événement `deleted` du `forceDelete()`.

Test : `tests/Feature/AiImporter/LunarBackupManagerTest` (vrai `ImportStagingToLunarJob` :
création + mise à jour puis restore — nom lisible via le cast, prix, stock, MPN, dimensions,
prix d'achat, pivot exact, produit créé supprimé, statuts staging ; jointure EAN ; prix ajouté
par l'import supprimé ; tags / caractéristiques / médias / documents / vidéos remis à
l'identique ; produit créé déjà en panier passé en brouillon ; réindexation Scout).

### 7.quinquies.17 Audit du module (2026-10-21) — API Claude, actions, pipeline

#### 7.quinquies.17.1 Appels Claude (`pko/ai-core`, `ClaudeProvider`)

**Problèmes** : la réponse était lue en `content.0.text` — les modèles à réflexion
active par défaut (Opus/Sonnet/Haiku 5.x) renvoient un bloc `thinking` **en premier**,
la valeur importée aurait été une chaîne vide sur toute la colonne. `stop_reason`
n'était pas lu (un `refusal` passait pour une réponse vide, une troncature `max_tokens`
était silencieuse). Le retry rejouait 3× **toute** erreur hors 401/402/403, dont les 400
(modèle inconnu) : 17 s de backoff perdues par ligne. La liste de modèles proposée dans
« Configuration LLM » s'arrêtait à Opus 4.7.

**Décisions** :

- **Lecture de la réponse** : premier bloc `type: text` du tableau `content`, les blocs
  `thinking` sont ignorés. `stop_reason: refusal` → `RuntimeException` (la ligne passe en
  erreur, motif `stop_details` dans les logs) ; `max_tokens` → texte tronqué renvoyé +
  `Log::warning`. Toujours en HTTP brut via le client Laravel (pas de SDK PHP dans le projet).
- **Retry** (trait `Llm\Concerns\RetriesTransientErrors`, partagé Claude/OpenAI) : seules
  les erreurs transitoires sont retentées — `ConnectionException`, 408, 409, 429, 5xx
  (dont 529 *overloaded*). Une 4xx définitive échoue immédiatement. L'en-tête
  `retry-after` d'un 429 prime sur le backoff configuré (plafond 60 s). Clé de config
  `ai-core.llm.retryable_status_codes` (remplace `critical_status_codes`).
- **Options de `LlmConfig::options`** reprises dans la requête : `max_tokens` (défaut
  `ai-core.llm.default_max_tokens` = 2048, auparavant 1024 codé en dur), `effort`
  (`low|medium|high|xhigh|max` → `output_config.effort`, recommandé `low` sur un import
  ligne à ligne), `temperature` (4.x uniquement, refusée par les 5.x). `testConnection()`
  passe `max_tokens: 16`.
- **Modèles** (`LlmProviderName::models()`) : Opus 5.5, Sonnet 5.5, Haiku 5.5, Fable 5.1
  en tête ; les identifiants 4.x restent listés pour que les configurations existantes se
  rechargent dans le select. Rejeté : un champ texte libre pour le modèle — le select
  pilote aussi le provider (`providerForModel`).
- **Prompt caching** : inchangé (bloc `system` + `cache_control` quand `ai.context_cache`),
  mais rappel : le cache n'est effectif qu'au-delà du minimum de tokens cacheables du
  modèle (1024 à 4096 selon le modèle) — un contexte global de quelques lignes n'est jamais
  mis en cache, seule une liste de handles autorisés volumineuse en bénéficie.

Tests : `LlmProvidersTest` (bloc thinking ignoré, `effort`/`max_tokens`/`cache_control`
dans le corps, refus → exception, 400 non retenté sans `Sleep`, 529 puis 429 `retry-after`
puis succès).

#### 7.quinquies.17.2 Action `llm_transform`

- **Résolution de la configuration** : `LlmConfig` et provider résolus **une fois par
  instance d'action** (cache process par identifiant, vidé en début de chaque
  `ParseFileToStagingJob` / `preview-config`). Avant : une requête SQL + une instanciation
  de provider **par ligne et par colonne IA**.
- **Sortie JSON** : les clôtures ```` ```json … ``` ```` sont retirées avant décodage ;
  `output_format: json` sans `output_json_key` renvoie le tableau décodé entier.
- `additional_context` vide n'est plus envoyé.

Tests : `LlmTransformActionTest` (no-op sans config active, corps de requête, une seule
requête `pko_llm_configs` pour deux lignes, fences JSON, `llm_config_id` en chaîne).

#### 7.quinquies.17.2bis `input_columns` = clés de sortie du mapping (fix 2026-10-22)

**Problème** : les `input_columns` d'un `llm_transform` (format PS : `name`, `manufacturer`,
`ean13`, `description_short`…) désignent des **clés de sortie du mapping**, pas des colonnes
brutes du fichier. L'action lisait `ctx->row[col]` (ligne brute, clés = en-têtes/lettres) →
tous les champs transmis au modèle étaient vides, et Claude répondait « Je ne peux pas
déterminer les catégories : tous les champs du produit sont vides ».

**Décisions** :

- `LlmTransformAction` lit chaque entrée dans `ExecutionContext::getOutput()` (valeur déjà
  transformée pour la ligne), repli sur la colonne brute si la clé n'est pas mappée. Les
  sorties structurées (hash `features`, listes) sont sérialisées en JSON.
- **Ordre de calcul** (`Support\MappingOrder::sorted()`, appliqué par `ParseFileToStagingJob`
  et `preview-config`) : colonnes sans IA d'abord dans l'ordre du mapping, puis colonnes IA
  triées par dépendances (`features` qui lit `description` passe après). Sans cela,
  `category` (17ᵉ du mapping somfy) lisait `manufacturer` (32ᵉ) avant son calcul. Cycle entre
  colonnes IA → ordre du mapping.

Tests : `MappingOrderTest`, `LlmTransformActionTest::test_input_columns_read_mapped_outputs_before_raw_row`.

#### 7.quinquies.17.3 Fabrique d'actions — coercition de types (`Action::filterConstructorParams`)

**Problème** : les configs PrestaShop portent les nombres en chaînes (`"llm_config_id": "1"`
dans `somfy.json`, `"value": "1.2"`, `"decimals": "2"`). Tous les fichiers d'action sont en
`strict_types=1` : une chaîne sur un paramètre `int`/`float` promu est un **`TypeError`** qui
mettait la ligne en erreur (non couvert : le test e2e PS n'avait pas de `llm_config_id`).
`ChangeCaseAction::fromArray` contournait aussi le filtre de clés inconnues : `{"type":
"uppercase", "comment": "…"}` levait `ArgumentCountError`.

**Décision** : la fabrique de base coerce les scalaires vers le type déclaré du paramètre
(`int`, `float` avec virgule décimale acceptée, `bool` depuis `1/0/true/false/oui/non`,
`string`). Chaîne vide → `null` si le paramètre est nullable, sinon clé omise (défaut du
constructeur) ; valeur non convertible → clé omise. `ChangeCaseAction` passe par le même
filtre. Tests : `ActionTypesTest::test_factory_coerces_numeric_strings_to_declared_scalar_types`,
`test_legacy_change_case_aliases_tolerate_unknown_keys`.

#### 7.quinquies.17.4 Pipeline et parser — allocations par ligne

- **`ActionPipeline`** : les instances d'actions sont mises en cache par hash de leur config
  résolue (borne 1000 entrées), au lieu de `ReflectionClass` + `new` à chaque ligne × colonne
  × action. Les actions sont immuables (propriétés `readonly`), un même JSON produit le même
  comportement. `config_data.sheets` est aussi mémorisé par job (lu à chaque
  `multiline_aggregate`). Les actions imbriquées d'un `condition` restent construites à la
  volée.
- **`SpreadsheetParser::readRow()` / `readHeaders()`** : `cellExists()` avant `getCell()` —
  PhpSpreadsheet 3.x **crée** la cellule demandée si elle n'existe pas ; sur une feuille
  creuse (colonnes vides en fin de ligne) la lecture matérialisait des millions de cellules
  vides en mémoire. Cellule absente → `null`, comme avant.
- **`ImportStagingToLunarJob`** : checkpoint de progression sur `imported + errors` (un lot
  d'erreurs consécutives ne rafraîchissait jamais la progression).

#### 7.quinquies.17.5 `ai-importer:import-ps-config`

- Le rapport de compatibilité lisait une liste de clés **figée et périmée** (annonçait
  `cost_price_cents`, `documents`, `tags`, `mpn`, `supplier`… comme « ignorées »). Il
  s'appuie désormais sur `LunarProductWriter::CANONICAL_KEYS` / `LEGACY_ALIASES` /
  `LEGACY_EURO_ALIASES`, constantes publiques qui sont aussi celles utilisées par
  `normalizeLegacyKeys()` — une clé ajoutée au writer doit être ajoutée là.
- Les clés PS de premier niveau `llm_global_context` (chaîne ou objet, sérialisé en JSON) et
  `llm_cache_context` sont reprises dans `config_data.ai.global_context` /
  `ai.context_cache`, la section lue par `LlmTransformAction` ; elles étaient perdues
  silencieusement. Test : `ImportPsConfigCommandTest::test_ps_llm_global_context_lands_in_ai_section`.

#### 7.quinquies.17.6 Points relevés, non traités

- `OpenAiProvider` envoie `max_tokens`, que les modèles OpenAI récents refusent au profit de
  `max_completion_tokens` ; liste de modèles OpenAI ancienne. Hors périmètre de cet audit.
- `PreviewConfigCommand` ne reproduit pas la résolution de la valeur initiale par feuille
  (`relation: one`) de `ParseFileToStagingJob` : l'aperçu d'une colonne issue d'une feuille
  secondaire peut différer du parse réel.
- `PrefixAction` est « idempotent » (ne re-préfixe pas une valeur qui commence déjà par
  `text`) : une référence qui commence légitimement par le préfixe n'est pas préfixée. Comportement
  verrouillé par test, conservé.
- Un `ImportLog` INFO est écrit par ligne parsée (volume = nombre de lignes du fichier).

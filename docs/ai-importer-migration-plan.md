# Plan de migration — Publiko AI Importer → `packages/pko/ai-importer`

Document d'architecture pour le portage du module PrestaShop 8 **Publiko AI Importer** vers un package Laravel 11 + Lunar 1.x + Filament 3 intégré au back-office.

Branche de travail : `ai-importer`.

---

## Objectif

Porter le système d'import produits modulaire PrestaShop (parsing Excel multi-feuilles → pipeline d'actions chaînées → staging → validation → import Lunar) avec :

- **Feature parity** avec le module PS (43 types d'actions, LLM, agrégation 1-N, conditions, cron resumable)
- **Perf** : fichiers de plusieurs milliers de lignes, UI tableaux paginés server-side
- **Stack-native** : Laravel Queues (pas de cron custom), Filament Resources (pas de DataTables), Eloquent (pas de Db::getInstance)
- **Extension propre** : Filament Plugin dans `packages/pko/ai-importer/`, aucune modif `vendor/`, aucune Resource Lunar custom pour les entités déjà couvertes

---

## Architecture cible

### 2.1 Package

```
packages/pko/ai-importer/
├── composer.json
├── config/ai-importer.php
├── database/migrations/        ← 5 tables pko_ai_importer_*
└── src/
    ├── AiImporterServiceProvider.php
    ├── Actions/                ← pipeline polymorphe
    │   ├── Action.php (abstract)
    │   ├── ActionRegistry.php
    │   ├── ExecutionContext.php
    │   └── Types/              ← 17 classes d'action (après simplification D)
    ├── Contracts/              ← interfaces (Action, LlmProvider)
    ├── Enums/                  ← JobStatus, StagingStatus, ErrorPolicy, LlmProviderName
    ├── Filament/
    │   ├── AiImporterPlugin.php
    │   └── Resources/
    │       ├── ImporterConfigResource.php
    │       ├── LlmConfigResource.php
    │       └── ImportJobResource.php
    ├── Jobs/
    │   ├── ParseFileToStagingJob.php
    │   └── ImportStagingToLunarJob.php
    ├── Llm/
    │   ├── LlmManager.php
    │   └── Providers/{ClaudeProvider, OpenAiProvider}.php
    ├── Models/                 ← 5 modèles Eloquent
    └── Services/
        ├── SpreadsheetParser.php
        ├── ActionPipeline.php
        └── LunarProductWriter.php
```

### 2.2 Enregistrement

- Namespace PSR-4 ajouté dans `composer.json` racine : `"Pko\\AiImporter\\": "packages/pko/ai-importer/src/"`
- `AiImporterServiceProvider` discovery auto (extra.laravel.providers dans composer du package)
- `AiImporterPlugin` déclaré dans `AppServiceProvider::register()` via `->plugin(AiImporterPlugin::make())`

### 2.3 NavigationGroup

Nouveau groupe Filament **« Imports »** (avant Configuration), visible uniquement pour les staff ayant la permission Shield `page_ImportJob`.

---

## Modèle de données

5 tables préfixe `pko_ai_importer_`. Mapping depuis les 5 tables `pko_*` du module PS.

### 3.1 `pko_ai_importer_configs`

Configurations de mapping par fournisseur.

| Col | Type | Note |
|---|---|---|
| `id` | bigint PK | |
| `name` | varchar(128) UNIQUE | "Somfy", "Fabdis generic" |
| `supplier_name` | varchar(255) NULL | Info libre, pas de FK (Lunar n'a pas de Supplier) |
| `description` | text NULL | |
| `config_data` | json | Schéma complet : `primary_sheet`, `join_key`, `sheets{}`, `mapping{}` |
| `timestamps` | | |

### 3.2 `pko_ai_importer_llm_configs`

Clés API + modèles LLM.

| Col | Type | Note |
|---|---|---|
| `id` | bigint PK | |
| `name` | varchar(64) UNIQUE | "Claude Sonnet prod", "OpenAI GPT-4o" |
| `provider` | varchar(32) | `claude` \| `openai` |
| `api_key` | text | **chiffré** via cast `encrypted` Laravel |
| `model` | varchar(64) | `claude-sonnet-4-6`, `gpt-4o`... |
| `options` | json NULL | provider-specific (temperature, max_tokens...) |
| `is_default` | bool default 0 | |
| `active` | bool default 1 | |
| `timestamps` | | |

Index : `provider`, `is_default`.

### 3.3 `pko_ai_importer_jobs`

Un job = un upload = un cycle complet parse + import.

| Col | Type | Note |
|---|---|---|
| `id` | bigint PK | |
| `uuid` | char(36) UNIQUE | identifiant public (URLs, logs) |
| `config_id` | FK configs nullOnDelete | |
| `input_file_path` | varchar(500) | `storage/app/ai-importer/inputs/{uuid}.xlsx` |
| `output_file_path` | varchar(500) NULL | CSV résultat (optionnel) |
| `status` | varchar(32) | parse : `pending/parsing/paused/parsed/error/cancelled` |
| `import_status` | varchar(32) | import : `pending/scheduled/importing/imported/error/rolled_back` |
| `total_rows` | int NULL | |
| `processed_rows` | int default 0 | |
| `chunk_size` | int default 500 | |
| `row_limit` | int NULL | mode test |
| `options` | json NULL | `{filter_mode, reference_column}` |
| `staging_count` | int default 0 | |
| `imported_count` | int default 0 | |
| `scheduled_at` | datetime NULL | import programmé |
| `error_policy` | varchar(32) default `ignore` | `ignore/stop/rollback` |
| `last_processed_row` | int NULL | checkpoint resume |
| `error_count` | int default 0 | |
| `can_resume` | bool default 1 | |
| `stopped_by_user` | bool default 0 | |
| `rollback_completed` | bool default 0 | |
| `parse_started_at` | datetime NULL | |
| `parse_completed_at` | datetime NULL | |
| `import_started_at` | datetime NULL | |
| `import_completed_at` | datetime NULL | |
| `backup_path` | varchar(500) NULL | |
| `error_message` | text NULL | |
| `queue_job_id` | varchar(64) NULL | | 
| `queue_batch_id` | char(36) NULL | `Bus::batch()` ID |
| `created_by_id` | FK lunar_staff nullOnDelete NULL | |
| `timestamps` | | |

Index : `status`, `import_status`, `scheduled_at`, `config_id`.

### 3.4 `pko_ai_importer_staging`

Une ligne parsée, prête à import.

| Col | Type | Note |
|---|---|---|
| `id` | bigint PK | |
| `import_job_id` | FK jobs cascadeOnDelete | |
| `row_number` | int | numéro ligne fichier source |
| `data` | json (LONGTEXT) | colonnes mappées {clé → valeur} |
| `status` | varchar(16) | `pending/validated/created/updated/imported/error/skipped/warning` |
| `error_message` | text NULL | |
| `lunar_product_id` | FK lunar_products nullOnDelete NULL | produit résultant |
| `validated_at` | datetime NULL | |
| `imported_at` | datetime NULL | |
| `timestamps` | | |

Index : `(import_job_id, status)`, `(import_job_id, row_number)`, `lunar_product_id`.

### 3.5 `pko_ai_importer_logs`

Logs structurés par job.

| Col | Type | Note |
|---|---|---|
| `id` | bigint PK | |
| `import_job_id` | FK jobs cascadeOnDelete | |
| `row_number` | int NULL | NULL = log général job |
| `level` | varchar(16) | `info/success/warning/error/debug` |
| `message` | text | |
| `context` | json NULL | payload additionnel |
| `created_at` | timestamp | `useCurrent()` |

Index : `import_job_id`, `(import_job_id, level)`, `(import_job_id, row_number)`.

---

## Pipeline d'actions (polymorphe)

### 4.1 Simplification (17 actions, pas 43)

Appliquer la **Proposition D** du document `SIMPLIFICATION.md` du module PS :

**Transformations simples** (sur la valeur courante) :
- `math` (opération +−×÷ + valeur) — remplace `multiply/divide/add/subtract`
- `round` (decimals)
- `change_case` (upper/lower/capitalize) — remplace 3 actions
- `trim` (chars, side)
- `truncate` (length, suffix)
- `slugify` (lowercase bool)
- `replace` (search, replace)
- `regex_replace` (pattern, replace)
- `date_format` (from, to)
- `validate_ean13`

**Combinaison multi-sources** :
- `concat` (sources[], separator)
- `template` (template, sources{})
- `copy` (col)

**Lookup** :
- `map` (values{}, default, multi_value bool) — fusionne `map` + `category_map`

**IA** :
- `llm_transform` (llm_config_id, prompt, input_columns, output_format, additional_context)

**Agrégation multi-feuilles** :
- `multiline_aggregate` (sheet, method, filter_type, separator, columns{})

**Flow** (pas une action, bloc parent) :
- **`condition`** : bloc `{branches: [{rules, logic, actions}], else_actions}` applicable à n'importe quelle colonne, évalué avant le pipeline.

### 4.2 Contract

```php
namespace Pko\AiImporter\Actions;

abstract class Action
{
    abstract public function execute(mixed $value, ExecutionContext $ctx): mixed;
    public static function fromArray(array $config): static { /* factory */ }
    abstract public static function type(): string;
}
```

### 4.3 Registry

```php
ActionRegistry::register('math', MathAction::class);
ActionRegistry::register('llm_transform', LlmTransformAction::class);
// ...
Action::make(['type' => 'math', 'operation' => 'multiply', 'value' => 1.2]);
```

### 4.4 Migration configs

Les configs JSON PS sont **déjà en pipeline v1** (tableau `actions[]`). Import direct possible via commande artisan :

```bash
php artisan ai-importer:import-ps-config path/to/config.json --name="Somfy"
```

Mapping colonnes PS → clés Lunar à fournir séparément (table de correspondance dans l'importeur).

---

## LLM providers

Strategy pattern, aligné sur PS. Manager Laravel :

```php
namespace Pko\AiImporter\Llm;

interface LlmProviderInterface
{
    public function transform(string $prompt, array $inputs, array $options = []): string;
    public function testConnection(): bool;
}

final class LlmManager
{
    public function forConfig(LlmConfig $config): LlmProviderInterface;
}
```

Providers :
- `ClaudeProvider` : API Anthropic, retry 3x exponential backoff
- `OpenAiProvider` : API OpenAI, retry 3x, support Assistants API (phase 2)

**Stockage clé API** : cast Eloquent `'api_key' => 'encrypted'` — utilise `APP_KEY` Laravel. Corrige le risque PS (clé stockée en clair).

Codes critiques (401/402/403) → lève `LlmCriticalException` qui arrête le job. 429 → retry + log warning.

---

## Workflow d'import

### 6.1 Étape 1 — Upload & config

Écran **« Nouveau job »** (Filament custom page `app/Filament/Pages/NewImportJob.php`) :
- `FileUpload` (xlsx/xls/csv, max 100 MB, disque `local`)
- `Select` config (options : tous les `ImporterConfig`)
- `Select` LLM config (par défaut : celle `is_default=true`)
- `TextInput` row_limit (optionnel, mode test)
- `Select` filter_mode (all / missing_only / existing_only)
- `DatePicker` scheduled_at (optionnel → import auto programmé)
- `Select` error_policy (ignore / stop / rollback)

Action « Démarrer le parse » → crée `ImportJob`, dispatche `ParseFileToStagingJob` sur queue dédiée.

### 6.2 Étape 2 — Parse (job queue)

`ParseFileToStagingJob` :
- Queue `ai-importer-parse` (workers séparés, timeout 3600s)
- Utilise `Bus::batch()` pour chunks (500 rows/batch par défaut)
- Chaque batch :
  1. Lit N rows du fichier via `SpreadsheetParser` (PhpSpreadsheet en itération, pas full-load)
  2. Applique pipeline actions via `ActionPipeline::run($row, $config)`
  3. Insert bulk staging (`StagingRecord::upsert(...)`)
  4. Update `ImportJob::processed_rows` + cache progress
- Checkpoint : `last_processed_row` persisté à chaque batch → resume possible
- Callbacks `then/catch/finally` → transition `status` → `parsed` / `error`

Progress : `Cache::put("ai-importer:job:{$id}:progress", $pct, 600)` polling Livewire 2s.

### 6.3 Étape 3 — Preview & validation

Écran **Détail job** (`ImportJobResource\Pages\ViewImportJob`) :
- Infos header : statut, progress, logs en temps réel (polling)
- **RelationManager** `StagingRecordsRelationManager` avec Filament Table :
  - Pagination server-side native
  - Filtre par `status` (SelectFilter)
  - Search global sur colonnes JSON (`where data->column like`)
  - Tri par colonnes dynamiques (via JSON path)
  - Bulk actions : valider, supprimer, changer statut
  - Row action `Edit` : modale avec form dynamique (colonnes du mapping)
- Tableau de logs (RelationManager `ImportLogsRelationManager`)

**Perf** : on évite le virtual scroll. Filament Table pagine en SQL → 50 rows/page. Sur 10 000 staging rows → 200 pages, navigation fluide.

### 6.4 Étape 4 — Import final (job queue)

Déclenché par :
- Bouton admin « Lancer l'import » (action Filament)
- OU automatiquement via `Schedule::command('ai-importer:run-scheduled')` toutes les 5 min → dispatche les jobs `import_status=pending` + `scheduled_at <= now()`

`ImportStagingToLunarJob` :
- Queue `ai-importer-import` (timeout 7200s)
- Backup (`LunarBackupManager::snapshot($jobId)`) : dump des tables Lunar concernées (produits cibles + prix + pivots) en JSON gzippé sur `storage/app/ai-importer/backups/`
- Itère staging rows (pending/validated) par chunks
- Pour chaque row :
  - Résolution produit existant via `reference` (SKU)
  - Si absent → `Product::create(...)` + `ProductVariant` + `Price` (en cents !)
  - Si présent → `Product::update(...)` selon mode (all/price_only/stock_only)
  - `attribute_data` → toujours `collect([new TranslatedText(['fr' => ...])])`
  - `collections()->syncWithoutDetaching([$id1, $id2])` via mapping catégories
  - `Features::syncByHandles($product, ['marque' => ['somfy'], ...])` (integration package catalog-features)
  - Stock : `ProductVariant::update(['stock' => $qty])` (pas de `ps_stock_available`)
  - Images : Spatie MediaLibrary `$product->addMediaFromUrl(...)`
  - Brand : `Brand::firstOrCreate(['name' => ...])`
  - Mise à jour `StagingRecord::status` → `created|updated|error`
- Checkpoint tous les 50 rows
- Politique erreur :
  - `ignore` : log + continue
  - `stop` : transition `error`, `last_processed_row` stocké, resume possible
  - `rollback` : replay backup, `import_status=rolled_back`

### 6.5 Rollback

Bouton action Filament « Rollback » sur un job `imported` :
- Charge le backup JSON
- Restore en transaction Eloquent
- `rollback_completed=true`

---

## Perf — patterns TreeManager réutilisés

Patterns validés sur TreeManager (500+ nœuds) réutilisés pour la preview staging (10 000+ rows) :

1. **Filament Table native** : pagination SQL server-side, pas de virtual scroll JS
2. **`#[Computed]` Livewire** pour tout listing → exclu du snapshot (payload léger)
3. **`skipRender()`** sur les actions qui ne nécessitent pas de re-rendu serveur
4. **`withCount()`** sur relations (évite N+1)
5. **JSON search** : `where('data->column', 'like', '%query%')` + generated column si besoin de perf critique
6. **Cache progress** : Redis `Cache::put(...)` au lieu de lire la DB à chaque polling

---

## Extension catalog-features

Dépendance du package sur `mde/catalog-features` pour les caractéristiques filtrables. Après création/update produit :

```php
use Pko\CatalogFeatures\Facades\Features;

Features::syncByHandles($product, [
    'marque'       => ['somfy'],
    'matiere'      => ['aluminium'],
    'applications' => ['residentiel', 'copropriete'],
]);
```

La règle `syncByHandles` ne touche **que** les familles listées — les caractéristiques non importées sont préservées. L'importeur expose une action `feature_sync` dans le pipeline pour mapper colonnes → handles de famille.

---

## Sécurité

- `declare(strict_types=1);` partout
- Clé API LLM **chiffrée** via cast Eloquent `encrypted`
- Shield policies auto-générées pour les 3 Resources + la page `NewImportJob`
- Upload fichier : validation MIME type + taille max (`config/ai-importer.php`)
- Chemin upload : `storage/app/ai-importer/inputs/{uuid}.{ext}` — pas d'accès public

---

## Phases & état

| Phase | Périmètre | État |
|---|---|---|
| **1 — Foundation** | Squelette package, migrations, modèles, providers, stubs | **Livré** (commit `4a66a3e`) |
| **2 — Actions + LLM + tests** | 17 actions polymorphes, ClaudeProvider + OpenAiProvider, tests unit/feature | **Livré** (26 tests verts) |
| **3 — Parse job** | `SpreadsheetParser` + `ProgressCache` + `ParseFileToStagingJob` réel | **Livré** |
| **4 — Import + backup** | `LunarProductWriter` + `LunarBackupManager` + `ImportStagingToLunarJob` + 3 politiques erreur | **Livré** |
| **5 — UI Filament** | RelationManagers staging + logs, header actions Launch/Rollback/Resume/Cancel | **Livré (partiel)** — éditeur visuel de config à venir |
| **6 — CLI + polish** | `ai-importer:import-ps-config` | **Livré** |

### Reste à faire (hors scope MVP)

- **Éditeur visuel de config** (Livewire + SortableJS, drag-n-drop actions, modales par type) — remplace le textarea JSON actuel
- **Image pipeline** : téléchargement URL distantes → Spatie MediaLibrary dans le writer (clé `images[]`)
- **Streaming XLSX** : chunked read filter PhpSpreadsheet pour fichiers >100k lignes
- **Tests LLM** avec clé API réelle (hors CI, en manuel)
- **ProductType / TaxClass dynamiques** via clés staging `product_type_handle` / `tax_class_handle`
- **Scheduler** : `Schedule::command('ai-importer:run-scheduled')` toutes les 5 min pour déclencher les jobs `scheduled_at <= now`

---

## Questions ouvertes

1. Tables natives Lunar écrites : validée la règle de ne **jamais** écrire en dehors de `Product`/`ProductVariant`/`Price`/`Collection`/`Brand`/`TaxClass` (pas de SQL raw) ?
2. Stockage fichiers : disque `local` par défaut. Bascule S3 envisagée en phase 6 ?
3. Reverb (WebSocket real-time) pour progress ou polling Livewire 2s suffit en phase 1 ?
4. Format export config : JSON brut ou versionné (`version: 2`) avec validation schéma ?
5. Import configs PS existantes : combien de configs à migrer ? Faut-il une mapping-table exhaustive colonnes PS → handles Lunar ?

---

_Créé : 2026-04-17. Mise à jour au fil des phases._

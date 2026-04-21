# CLAUDE.md — Instructions projet

## Contexte projet

Back-office **Laravel 11 + Lunar 1.x + Filament 3** e-commerce B2B (domaine configurable via Back-office → Storefront → Paramètres → Identité). Ce fichier contient uniquement les **instructions**. Toute la documentation technique (stack, choix, architecture, packages, shipping, paiements, gotchas Lunar…) vit dans `docs/`.

**Ne consulte `docs/` que dans deux cas** :
1. Quand tu prépares un **Plan Mode** ou un plan d'implémentation pour une demande non triviale
2. Quand tu travailles sur une **grosse feature** (plusieurs fichiers, plusieurs packages, ou impact architectural)

Pour toute tâche courte/ciblée (bug fix, petit ajout, question directe), **ne charge pas** les `docs/` — réponds ou code directement à partir du contexte de la conversation et du code du projet. La règle de **mise à jour** de la doc au commit (§1) s'applique toujours, elle. Pour la navigation : `docs/README.md` liste l'ensemble des fichiers thématiques.

---

## 1. Documentation projet — où chercher, quand écrire

### Fichiers de référence

| Fichier | Rôle |
|---|---|
| `docs/README.md` | **Index** de la documentation technique (TOC de tous les fichiers thématiques) |
| `docs/architecture.md` | Stack, Docker, extension Lunar, gotchas, arborescence, à éviter |
| `docs/packages-architecture.md` | Path repositories composer + foundation media-core + checklist nouveau package |
| `docs/admin.md` | Navigation Filament, édition produit unifiée, liste, global search |
| `docs/workflow.md` | Tests, Git, MCP servers, RBAC |
| `docs/payments.md`, `docs/shipping.md` | Paiements Stripe, drivers shipping |
| `docs/packages/<pkg>.md` | **Un fichier par package PKO** (catalog-features, media-core, page-builder, storefront-cms, api-platform, loyalty, ai-*, etc.) |
| `cahier-des-charges.md` (racine) | Cahier des charges contractuel |
| `CLAUDE.md` (ce fichier) | Instructions comportementales pour toi uniquement — jamais de choix techniques ici |

### Règle de maintenance documentaire — OBLIGATOIRE

**Avant chaque commit**, si ton changement introduit **l'un** des éléments suivants, tu **DOIS** mettre à jour le fichier `docs/` concerné (ou créer un nouveau fichier thématique dans `docs/` ou `docs/packages/` si le sujet mérite son propre document) **dans le même commit** :

- Une nouvelle décision technique ou un arbitrage non trivial
- Une nouvelle dépendance Composer ou NPM
- Une nouvelle variable d'environnement
- Une nouvelle table ou migration structurante
- Un nouveau package interne sous `packages/pko/*`
- Une nouvelle règle de codage ou de workflow
- Un nouveau driver (paiement, shipping, search, etc.)
- L'installation ou la configuration d'un MCP server
- Le rejet documenté d'une alternative technique (pourquoi pas X)

La doc est partie intégrante du deliverable. Un commit qui introduit une décision sans mettre à jour la doc est considéré incomplet.

**Règle d'atomicité** : ne pas créer de fichier fourre-tout. Un nouveau package PKO → nouveau `docs/packages/<pkg>.md`. Une décision transverse → enrichir le fichier thématique existant (`architecture.md`, `workflow.md`, etc.). Pas de "misc.md" ou "choices.md" monolithique.

### Mise à jour de la mémoire brain² (Obsidian)

Slug du projet : `ecom-laravel`

Notes dans `~/webdev/projects/brain²/vault/wiki/projects/ecom-laravel/`.

Règles :
- **Harness / outils** : `~/.claude/CLAUDE.md` §brain²
- **Contenu / format / linking** (source de vérité) : `~/webdev/projects/brain²/vault/CLAUDE.md`

---

## 2. Utilisation OBLIGATOIRE des MCP servers

Deux serveurs MCP sont configurés dans `.mcp.json` à la racine du projet :

| Serveur | Transport | Couverture |
|---|---|---|
| **`laravel-boost`** | stdio (Docker) | Laravel 11, Filament 3, Livewire 3, PHP 8.3, Pint, Pest, Tailwind, schéma DB live, logs applicatifs, tinker, routes |
| **`lunar-docs`** | HTTP (remote) | Documentation officielle Lunar v1.x (search + fetch de pages `.mdx`) |

### Règles d'utilisation — non-négociables

1. **Dès qu'une question touche Laravel / Filament / Livewire / Pest / Pint / Tailwind / schéma DB / routes / artisan / config** → tu **DOIS** utiliser `mcp__laravel-boost__*` **avant** toute autre source. Ne réponds jamais de mémoire sur ces sujets.

2. **Dès qu'une question touche Lunar** (core, admin, cart, order, product, variant, collection, attribute, tax, discount, shipping, stripe, paypal, search, channel, customer…) → tu **DOIS** utiliser `mcp__lunar-docs__*` **avant** toute autre source. Ne jamais fouiller `vendor/lunarphp/*` en premier, ne jamais répondre de mémoire.

3. **Context7** reste le fallback pour les packages tiers non couverts par les deux MCPs ci-dessus (ex : `kalnoy/nestedset`, `bezhansalleh/filament-shield`, SDK SOAP Chronopost/Colissimo).

4. **Fouiller `vendor/`** est autorisé **uniquement** :
   - En dernier recours quand les MCPs ne répondent pas
   - Pour vérifier une signature exacte quand la doc manque d'un détail d'implémentation
   - Jamais en premier réflexe

5. Si un MCP renvoie une erreur ou « no results » → essaie une requête reformulée avant de tomber en fallback. Et logue le fait dans ta réponse à l'utilisateur.

---

## 3. Règles techniques non-négociables

### 3.0 Réutilisabilité & branding — NON-NÉGOCIABLE

Ce back-office est conçu pour être **réutilisé sur n'importe quelle boutique**. Aucun nom de marque, aucune référence au client final, aucune donnée métier spécifique ne doit être codée en dur. La seule marque qui peut apparaître dans le code (dossiers, namespaces, noms de packages, handles techniques) est **`publiko` / `pko`** — **jamais dans l'UI utilisateur**.

**Règles** :

1. **Nom de la boutique, logo, tagline, meta description, contact, réseaux sociaux, USPs, bannières, etc.** → **toujours** lus depuis `Pko\StorefrontCms\Models\Setting` (table `pko_storefront_settings`), éditables depuis la page Filament **Storefront → Paramètres**. Aucun `echo 'Nom Boutique'` dans une vue Blade ni dans un `$title`, `$description`, `brandName()`, etc.
2. **Helpers disponibles** (auto-loadés via `composer.json` → `autoload.files`) : `brand_name()`, `brand_tagline()`, `brand_meta_description()`. Fallback automatique vers `config('app.name')` si le Setting est vide.
3. **Packages custom** : tous sous `packages/pko/*`, namespace racine `Pko\*`. Préfixe `pko` ou `publiko` autorisé dans le **code** (dossier, namespace, nom de package Composer, handle technique, alias Livewire, permission Shield, préfixe de table DB, classe). **Interdit** dans tout ce qui est rendu à l'utilisateur final (label Filament, titre de page, meta, e-mail, notification, view, string traduisible).
4. **Données de seed** : les seeders peuvent contenir de la demo-data avec n'importe quel nom (c'est juste de la data remplaçable par `make fresh`). Ce n'est pas un test de branding.
5. **Variables d'environnement** : noms neutres (`SHIPPER_NAME`, `ADMIN_EMAIL`, `CONTACT_PHONE`, `LOYALTY_RATIO`…), **jamais** préfixées par un nom de client. Défauts vides ou génériques.
6. **Fichiers de config** (`packages/pko/*/config/*.php`) : noms neutres (`storefront.php`, `loyalty.php`, `chronopost.php`…), **pas** de préfixe marque dans la clé (`config('storefront.contact')`, pas `config('pko-storefront.contact')` ni `config('mde-storefront.contact')`).
7. **Préfixes de tables DB** : toujours `pko_` pour les tables custom (jamais un nom de client). Jamais modifier les tables Lunar.
8. **Avant chaque commit** : vérifier qu'aucun nom de client/marque ne s'est glissé en dur. Utiliser le MCP JetBrains (`search_in_files_by_regex`) ou `grep` avec un pattern `\b<nom-client>\b` (case-insensitive) sur tout sauf `vendor/`, `node_modules/`, `database/seeders/`, `.env`, `cahier-des-charges*.md` et docs client externes.

### 3.1 Règles techniques

1. **Jamais modifier `vendor/`** — aucun patch, aucune exception. Toute personnalisation passe par les mécanismes d'extension documentés dans `docs/architecture.md`.
2. **`declare(strict_types=1);`** en tête de chaque fichier PHP que tu crées ou touches.
3. **PSR-12** — lance `make lint` avant chaque commit. Si rouge → corrige avant de committer.
4. **Migrations custom** : préfixe de table `pko_`, dans `database/migrations/` (ou `packages/pko/<module>/database/migrations/` si spécifique à un module packagé).
5. **Modules métier** : dans `packages/pko/*`, enregistrés comme Filament Plugin, **jamais** en modifiant le core Lunar ni les resources Lunar Admin.
6. **Prix en cents** — toujours des entiers. `19900` = 199,00 €. Jamais de float pour les montants.
7. **`attribute_data` Lunar** — toujours une collection de `Lunar\FieldTypes\*` (`Text`, `TranslatedText`, `Number`, `Dropdown`…), jamais de strings bruts.
8. **Laravel Cashier interdit** pour encaisser une commande Lunar. Réservé à d'éventuels abonnements dédiés.
9. **Resources Filament custom interdites en phase 1** pour les entités déjà couvertes par Lunar Admin (produits, variantes, collections, prix, commandes, clients, taxes, promos, livraison, marques, tags, canaux, devises, staff). Étendre via `LunarPanel::extensions()` / `ResourceExtension` à la place.
10. **Policies Shield** — régénérées automatiquement par `make install`. Ne pas éditer à la main, sauf override explicite documenté dans `docs/`.
11. **Service Docker** = `app` (pas `laravel.test`, pas `sail`). Stack custom Traefik + phpMyAdmin.

### 3.2 Création d'un nouveau package PKO — NON-NÉGOCIABLE

Tous les modules custom vivent sous `packages/pko/<feature>/` et sont installés via **path repositories** composer. **Ne jamais** ajouter d'entrée PSR-4 dans le `composer.json` racine ni de provider dans `bootstrap/providers.php` — tout passe par l'auto-discovery du package lui-même.

#### Checklist obligatoire à la création

1. **Nommage** :
   - Dossier : `packages/pko/<feature-kebab>/` (ex: `packages/pko/my-feature/`)
   - Namespace : `Pko\MyFeature\` (PascalCase)
   - Composer : `pko/lunar-<feature-kebab>` dans `name` (ex: `pko/lunar-my-feature`)
   - ServiceProvider : `Pko\MyFeature\MyFeatureServiceProvider`

2. **Fichiers obligatoires** à la racine du package :
   - `composer.json` avec `name`, `description`, `type: "library"`, `license: "proprietary"`, `require` (incluant toutes les cross-deps `pko/lunar-*` nécessaires), `autoload.psr-4`, et **impérativement** `extra.laravel.providers` pour auto-discovery
   - `README.md` minimal : description 1-phrase + install + dépendances
   - `src/<Feature>ServiceProvider.php` : provider qui `loadMigrationsFrom`, `loadViewsFrom`, `loadRoutesFrom`, `loadTranslationsFrom` selon le besoin

3. **i18n minimal** : toute Filament Resource/Page avec `navigationLabel`/`modelLabel`/`pluralModelLabel` doit wrapper ces labels avec `__()` + fournir un fichier `lang/fr/admin.php` dans le package. Pattern :
   ```php
   public static function getNavigationLabel(): string
   {
       return __('pko-<feature>::admin.<resource>.nav');
   }
   ```
   Le `ServiceProvider::boot()` doit appeler `loadTranslationsFrom(__DIR__.'/../lang', 'pko-<feature>')` et `publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/pko-<feature>')], 'pko-<feature>-lang')`.

4. **Foundation média** : si le package stocke des fichiers (images, PDFs) attachés à un modèle, il doit déclarer `"pko/lunar-media-core": "@dev"` dans ses `require` et utiliser le trait `Pko\LunarMediaCore\Concerns\HasMediaAttachments` + le composant Filament `Pko\LunarMediaCore\Filament\Forms\Components\MediaPicker`. **Jamais** recréer son propre pivot polymorphique.

5. **Branding interdit** : aucun nom de client dans le nom de package, le namespace, les labels, les seeders (cf. §3.0). Seul le préfixe `pko`/`publiko` est autorisé dans le code.

6. **Enregistrement root** : ajouter l'entrée `"pko/lunar-<feature>": "@dev"` dans `require` du `composer.json` racine. Le `repositories[type=path, url=packages/pko/*]` est déjà en place et découvrira automatiquement le nouveau dossier.

7. **Ce qu'il ne faut PAS faire** :
   - ❌ Ajouter le namespace dans `autoload.psr-4` du `composer.json` racine (tout est dans le composer.json du package)
   - ❌ Ajouter le ServiceProvider dans `bootstrap/providers.php` (auto-discovery)
   - ❌ Ajouter `autoload.files` dans le root pour les helpers du package (mettre `"files"` dans le `composer.json` du package)

#### Swap de Resource Lunar — pattern obligatoire

Si on subclass une Resource Lunar (ex: `PkoProductResource extends ProductResource`), **override obligatoire** de `getDefaultPages()` avec des sous-classes de pages qui redéclarent `$resource` vers la Pko-variante. Sinon Lunar's ListPage (qui hardcode `$resource = ProductResource::class`) génère des URLs edit/create vers une route inexistante après le swap → `RouteNotFoundException`.

Pattern :
```php
// PkoProductTypeResource.php
public static function getDefaultPages(): array
{
    return [
        'index' => PkoListProductTypes::route('/'),
        'create' => PkoCreateProductType::route('/create'),
        'edit' => PkoEditProductType::route('/{record}/edit'),
    ];
}

// PkoProductTypeResource/Pages/PkoListProductTypes.php
class PkoListProductTypes extends \Lunar\Admin\...\ListProductTypes
{
    protected static string $resource = PkoProductTypeResource::class;
}
```

S'applique aux Resources swappées via `$resources` reflection dans `AppServiceProvider::swapLunarResources()` (PkoProductResource, PkoProductTypeResource, PkoProductOptionResource, PkoAttributeGroupResource, PkoCollectionGroupResource).

---

## 4. Workflow de commit

1. **Avant de committer** :
   - `make test` doit être vert
   - `make lint` doit être vert
   - Le fichier `docs/` concerné mis à jour si le commit introduit une décision/dépendance/env var/table/règle (voir §1)

2. **Conventional Commits obligatoires** : `feat:`, `fix:`, `refactor:`, `chore:`, `docs:`, `test:`, `perf:`, `build:`.

3. **Interdits dans les messages de commit** :
   - Toute mention de Claude, Anthropic, Claude Code, AI, IA
   - `Co-Authored-By: Claude`
   - Emojis générateurs (🤖, 🧠…)

4. **Interdits en git** sauf demande explicite utilisateur :
   - `--no-verify` (skip hooks)
   - `--no-gpg-sign`
   - `git push --force` sur `main` / `develop`
   - `git reset --hard` sans backup préalable
   - `git rebase -i` (flag interactif)

5. **Propose toujours un commit** à l'utilisateur après un développement fonctionnel terminé — mais ne commit jamais sans son accord explicite.

6. **Branche git** : si la branche courante est `main`, créer une branche `feat/<slug>` (ou `fix/<slug>`) **avant la première édition de fichier**. Si on est déjà sur une branche feature, continuer sans en créer une nouvelle.

---

## 5. Commandes Make essentielles

| Commande | Effet |
|---|---|
| `make install` | Installation complète (build + migrate + `lunar:install` + Shield + seed) |
| `make up` / `make down` | Démarrer / arrêter la stack Docker |
| `make shell` | Shell interactif dans le conteneur `app` |
| `make fresh` | `migrate:fresh --seed` (reset DB complet) |
| `make test` | Suite PHPUnit complète |
| `make lint` | Laravel Pint (PSR-12) |
| `make artisan CMD='...'` | Commande artisan arbitraire |
| `make composer CMD='...'` | Commande composer arbitraire |

Toute commande PHP/Artisan/Composer doit passer par Make (ou `docker compose exec -u sail app …`). Ne lance jamais `php artisan` directement sur l'hôte.

---

## 6. Interdictions dures (rappel synthèse)

- **Coder en dur un nom de client / de marque** dans l'UI utilisateur (vue, label, titre, meta, notif, e-mail) — passer par `brand_name()` / `Setting::get('brand.*')`
- Préfixer un fichier / une classe / une env var / une clé de config avec un nom de client (seul `pko` / `publiko` est autorisé, et uniquement dans le code, jamais dans l'UI)
- Modifier `vendor/` — **jamais**
- Modifier les migrations Lunar publiées — créer une migration custom dédiée qui ajoute les colonnes via `Schema::table()`
- Toucher `config/lunar/*.php` sans raison forte (facilite les mises à jour)
- Créer une Filament Resource pour une entité déjà couverte par Lunar
- Utiliser Cashier pour encaisser
- Répondre de mémoire sur Laravel/Filament/Livewire/Lunar sans avoir interrogé les MCPs
- Committer du code qui introduit une décision technique sans mettre à jour `docs/`
- Mentionner Claude/Anthropic dans un commit
- Ajouter un package PKO dans `composer.json` racine → `autoload.psr-4` (cf §3.2 : chaque package a son propre composer.json + path repository)
- Enregistrer un provider PKO dans `bootstrap/providers.php` (auto-discovery obligatoire via `extra.laravel.providers`)
- Créer un pivot polymorphique pour attacher des médias à un modèle — utiliser `pko/lunar-media-core` (trait `HasMediaAttachments` + table `pko_mediables`)
- Subclasser une Resource Lunar sans override de `getDefaultPages()` avec sous-classes redéclarant `$resource` (cf §3.2 : bug pages Lunar avec `$resource` hardcodé)

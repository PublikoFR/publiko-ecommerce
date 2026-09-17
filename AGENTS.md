# AGENTS.md — Instructions projet

> `CLAUDE.md` ne fait qu'importer ce fichier (`@AGENTS.md`) : n'éditer que `AGENTS.md`.

Back-office **Laravel 11 + Lunar 1.x + Filament 3**, e-commerce B2B **réutilisable sur n'importe quelle boutique**. Ce fichier ne contient que des **instructions**. Toute la documentation technique vit dans `docs/` (index : `docs/README.md`).

**Lis `docs/` seulement** pour un Plan Mode, une grosse feature (plusieurs fichiers/packages, impact archi), ou quand un déclencheur ci-dessous l'exige. Pour un bug fix ou un petit ajout, code directement.

## Les 4 règles qui ne souffrent aucune exception

1. **Aucun nom de client ou de marque en dur** dans ce qui est rendu à l'utilisateur (§3.0).
2. **Jamais modifier `vendor/`** ni les migrations/tables Lunar.
3. **Doc à jour dans le même commit** quand le changement introduit une décision (§1).
4. **Jamais la suite complète de tests de ta propre initiative** — tests ciblés uniquement (§4).

---

## 1. Documentation — quand écrire

**Avant chaque commit**, si le changement introduit l'un de ces éléments, mets à jour le fichier `docs/` concerné **dans le même commit** (ou crée `docs/packages/<pkg>.md` / un fichier thématique) :

- décision technique ou arbitrage non trivial, rejet argumenté d'une alternative
- dépendance Composer/NPM, variable d'environnement, table ou migration structurante
- nouveau package `packages/pko/*`, nouveau driver (paiement, shipping, search…)
- nouvelle règle de codage/workflow, installation ou configuration d'un MCP server

Pas de fichier fourre-tout (`misc.md`, `choices.md`) : enrichir le fichier thématique existant.

**brain²** : slug `ecom-laravel`, notes dans `~/webdev/projects/brain²/vault/wiki/projects/ecom-laravel/`. Règles de contenu : `~/webdev/projects/brain²/vault/CLAUDE.md`.

## 2. MCP servers — avant de répondre de mémoire

- **Laravel / Filament / Livewire / Pest / Pint / Tailwind / schéma DB / routes / artisan / config** → `laravel-boost` d'abord.
- **Lunar** (product, variant, cart, order, tax, discount, shipping, customer…) → `lunar-docs` d'abord, pas `vendor/lunarphp/*`.
- **Autres packages tiers** (`kalnoy/nestedset`, `filament-shield`, SDK Chronopost/Colissimo…) → Context7.
- `vendor/` en lecture seulement en dernier recours ou pour vérifier une signature exacte.
- MCP en erreur ou sans résultat → reformuler une fois, puis fallback, et le signaler dans la réponse.

## 3. Règles techniques

### 3.0 Réutilisabilité & branding

- Nom, logo, tagline, meta, contact, réseaux, USPs, bannières → **toujours** lus depuis `Pko\StorefrontCms\Models\Setting` (`pko_storefront_settings`, page **Storefront → Paramètres**). Helpers : `brand_name()`, `brand_tagline()`, `brand_meta_description()`.
- `pko` / `publiko` autorisé **dans le code uniquement** (dossier, namespace, package Composer, handle, alias Livewire, permission Shield, préfixe de table, classe). **Interdit** dans l'UI : label Filament, titre, meta, e-mail, notification, vue, string traduisible.
- Env vars et clés de config neutres (`SHIPPER_NAME`, `config('storefront.contact')`), jamais préfixées par un client ni par `pko-`.
- Les seeders peuvent contenir de la demo-data nommée (remplaçable par `make fresh`).
- **Avant commit** : `grep -rni '\b<nom-client>\b'` hors `vendor/`, `node_modules/`, `database/seeders/`, `.env`, `cahier-des-charges*.md`.

### 3.1 Code

1. `declare(strict_types=1);` en tête de tout fichier PHP créé ou touché. PSR-12 via `make lint`.
2. Prix en **cents** (entiers). Jamais de float pour un montant.
3. `attribute_data` Lunar = collection de `Lunar\FieldTypes\*`, jamais de strings bruts.
4. Tables custom préfixées `pko_`. Colonnes ajoutées aux tables Lunar via migration custom `Schema::table()`. Ne pas toucher `config/lunar/*.php` sans raison forte.
5. Modules métier dans `packages/pko/*`, enregistrés comme Filament Plugin.
6. **Pas de Filament Resource custom** pour une entité couverte par Lunar Admin (produits, variantes, collections, prix, commandes, clients, taxes, promos, livraison, marques, tags, canaux, devises, staff) → `LunarPanel::extensions()` / `ResourceExtension`.
7. **Laravel Cashier interdit** pour encaisser une commande Lunar.
8. Policies Shield générées automatiquement, ne pas les éditer à la main.
9. Service Docker = `app`. Toute commande PHP passe par `make` (ou `docker compose exec -u sail app …`), jamais `php artisan` sur l'hôte.

### 3.2 Packages PKO et Resources Filament — déclencheurs

- **Créer un package `packages/pko/*`** → lis d'abord la checklist de `docs/packages-architecture.md`. Rappels : auto-discovery via `extra.laravel.providers`, **jamais** d'entrée dans `autoload.psr-4` racine ni dans `bootstrap/providers.php` ; médias attachés → `pko/lunar-media-core` (trait `HasMediaAttachments`), jamais de pivot polymorphique maison.
- **Subclasser une Resource Lunar** → override obligatoire de `getDefaultPages()` avec des sous-classes de pages redéclarant `$resource` (sinon `RouteNotFoundException`). Pattern et cas des clusters : `docs/packages-architecture.md`.
- **Créer une Resource / Page / Cluster Filament** → `make shield-sync` avant de considérer le travail terminé (sinon absente de la sidebar).

### 3.3 Front Office — Design System

Tout le Front Office (vues, Livewire, e-mails client) suit le design system de `design-system/`. **Avant de toucher une vue front**, lis les règles de `design-system/README.md`. L'essentiel : tokens Tailwind/CSS uniquement (jamais de hex en dur), **un seul CTA lime par vue**, icônes Lucide, réutiliser les composants `packages/pko/storefront/resources/views/components/*`, marque toujours via `Setting`/`brand_name()`.

## 4. Tests et commits

1. **Tests** : `make test-only T=<chemin>` sur les tests liés au changement, vert avant commit ou merge. `make test` et `npm run test:e2e` **uniquement** sur demande explicite ou avant un déploiement en **PRODUCTION** (une suite coupée détruit la base `testing`).
2. `make lint` vert avant commit.
3. **Conventional Commits** : `feat:`, `fix:`, `refactor:`, `chore:`, `docs:`, `test:`, `perf:`, `build:`.
4. **Messages de commit** : aucune mention d'assistant IA (Claude, Anthropic, Claude Code, Codex…), pas de `Co-Authored-By`, pas d'emoji générateur. Un scope technique comme `ai-importer` reste autorisé.
5. **Git interdit sauf demande explicite** : `--no-verify`, `--no-gpg-sign`, `push --force` sur `main`/`develop`, `reset --hard` sans backup, `rebase -i`.

**Session interactive** (humain dans la boucle) : sur `main`, créer `feat/<slug>` ou `fix/<slug>` **avant la première édition** ; proposer le commit en fin de dev, ne jamais committer sans accord.

**Task Kanban TIMON** (worktree `timon/task/<id>`) : le brief vaut accord. Committer dans la branche du worktree sans demander, ne pas créer de branche `feat/`, laisser le pipeline faire review et merge.

## 5. Commandes Make

| Commande | Effet |
|---|---|
| `make up` / `make down` | Démarrer / arrêter la stack |
| `make install` | Installation complète (build, migrate, `lunar:install`, Shield, seed) |
| `make fresh` | `migrate:fresh --seed` |
| `make shell` | Shell dans le conteneur `app` |
| `make test-only T=...` | Tests ciblés — **défaut avant commit** |
| `make test` / `npm run test:e2e` | Suite complète / E2E — **sur demande ou avant prod** |
| `make lint` | Pint (PSR-12) |
| `make shield-sync` | Policies Shield + super-admin + `optimize:clear` |
| `make artisan CMD='...'` / `make composer CMD='...'` | Artisan / Composer |

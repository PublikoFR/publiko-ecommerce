# Workflow — tests, git, IA, RBAC

## Autorisations (RBAC)

**Décision** : `bezhansalleh/filament-shield` 3.x.

**Pourquoi** :

- Plugin Filament 3 officiel qui génère automatiquement les policies pour toutes les ressources Lunar découvertes.
- UI admin pour créer rôles + permissions.
- Scope par `panel_id = 'admin'` (séparation claire des permissions front vs back-office futur).

**Rôles suggérés** :

- `super_admin` — accès total
- `catalogue_manager` — produits, catégories, marques, taxes, médias
- `sav` — commandes, clients, retours
- `lecture_seule` — lecture uniquement pour audit/reporting

**Installation automatisée** : `make install` lance `shield:install admin` puis `shield:generate --all --panel=admin` pour générer toutes les policies d'un coup.

### 6.1 Credentials admin de dev — source de vérité

Un seul point de création du compte admin : **`PkoAdminUserSeeder`**, premier seeder appelé par `DatabaseSeeder`. Idempotent (`updateOrCreate` par email), il garantit qu'après un `make fresh` ou `make install` le compte admin est toujours restauré avec les mêmes credentials.

Credentials par défaut :
- Email : `admin@example.fr`
- Password : `testing123`

Overridables via les variables d'env `ADMIN_EMAIL` et `ADMIN_PASSWORD` (utile pour environnements non-locaux).

Le Makefile enchaîne dans `install` et `fresh` : `migrate[:fresh]` → `lunar:install` → `shield:generate` → `db:seed` (crée Staff id=1) → `shield:super-admin --user=1` (assigne le rôle `super_admin`). La commande `lunar:create-admin` n'est plus utilisée (remplacée par le seeder).

---

## Internationalisation (i18n)

`APP_LOCALE=fr`, `APP_FALLBACK_LOCALE=en`. Les messages de validation, d'auth et de
reset password **doivent** être en français : les fichiers `lang/fr/validation.php`,
`lang/fr/auth.php`, `lang/fr/passwords.php` fournissent la traduction complète. Sans
`lang/fr/validation.php`, Laravel retombe sur le fallback `en` → messages anglais dans
tous les formulaires (front + Filament côté Laravel). Toute nouvelle chaîne d'erreur
métier custom (ex. SIRET, paiement) doit être écrite directement en français.

---


## Tests

**Framework** : PHPUnit 11 (pas Pest — volonté d'avoir une syntaxe unique avec le reste de l'écosystème Laravel/Lunar).

**Organisation** :

- `tests/Unit/` — tests sans Laravel bootstrap (helpers purs, DTOs, calculs)
- `tests/Feature/` — tests avec `RefreshDatabase` (routes, seeders, jobs)

**Tests livrés** :

- `AdminPanelAccessTest` — guard redirect sur `/admin`, login page accessible
- `SeedersTest` — vérifie 50 produits / ≥3 collections / 2 groupes clients / 10 commandes / ≥5 marques + zone shipping FR / 3 méthodes / 3 rates
- `Unit\Shipping\ZoneResolverTest` — cas France métropolitaine, Corse, DOM, étranger, input invalide
- `Unit\Shipping\ChronopostQuoteTest` — grille tarifaire, services activés, max weight
- `Unit\Shipping\ColissimoQuoteTest` — grille + surcharge signature DOS
- `Unit\CatalogFeatures\FeatureModelsTest` — ordre par position, cascade delete family→values, unicité handle par famille, scope `global()`
- `Feature\CatalogFeatures\FeatureManagerTest` — attach/detach/sync + events, `syncByHandles` préserve les familles non listées, `familiesFor()` mix globales + rattachées, `productsWith()` filtre AND

**Règle** : ajouter des tests pour tout pipeline critique (calcul de prix, stock, cycle de vie commande, création d'envoi transporteur).

### Gotchas tests Filament / Lunar

- **Auth des pages du panel `lunar`** : le panel Lunar Admin utilise `->authGuard('staff')` (cf. `LunarPanelManager::defaultPanel()`). Un `Livewire::test()` d'une Page du panel rend le chrome (sidebar) qui énumère les resources Lunar et appelle `BaseResource::canAccess()` → `Filament::auth()->user()` résolu **sur le guard `staff`**. Authentifier un Staff sur ce guard, pas un User sur le guard web :

  ```php
  $admin = \Lunar\Admin\Models\Staff::query()->first(); // seedé par PkoAdminUserSeeder
  $this->actingAs($admin, 'staff');
  ```

  `actingAs($user)` (guard web par défaut) laisse `Filament::auth()->user()` null → `Call to a member function can() on null`.

- **Fixtures sur modèles Lunar guarded** : `Lunar\Models\Product` est `$guarded = ['*']` avec un `$fillable` restreint (`attribute_data, product_type_id, status, brand_id`). Un `$model->update([...])` en mass-assignment **droppe silencieusement** les colonnes custom `pko_*` non fillable (ex. `pko_supplier_id`). Pour poser un état de fixture déterministe, utiliser `forceFill([...])->save()` (ou affecter les attributs en direct), pas `update()`.

---


## Isolation de la base `testing` par worktree PKOS

**Problème** : la base `testing` est partagée entre tous les agents qui tournent sur le même hôte Docker. Deux runs concurrents (deux worktrees) lancent `migrate:fresh` sur `testing` → deadlock sur `DROP TABLE`, erreur `1050 table already exists`, migrations corrompues, et éventuellement kill du process PHP (artefact de connexion zombie, historiquement confondu avec un segfault xdebug).

**Solution** : `make test` depuis un worktree crée et utilise automatiquement une base isolée `testing_<slug>` (dérivée du task ID PKOS, 12 premiers chars sans tirets). Chaque worktree a sa propre base → zéro interférence.

**Détail technique** :
1. `phpunit.xml` déclare `<env name="DB_DATABASE" value="testing"/>` **sans** `force="true"` → une env var posée par `docker compose exec -e` prend la priorité.
2. Le Makefile dérive `WT_DB_NAME := testing_$(shell basename $(CURDIR) | tr -d '-' | cut -c1-12)` en worktree.
3. Avant le run : création + GRANT via root MySQL (l'user applicatif `weklo` n'a pas `CREATE DATABASE`) :
   ```sql
   CREATE DATABASE IF NOT EXISTS `testing_<slug>` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL PRIVILEGES ON `testing_<slug>`.* TO 'weklo'@'%';
   FLUSH PRIVILEGES;
   ```
   Mot de passe root : lu depuis `.env` (`DB_ROOT_PASSWORD`) ou valeur par défaut `root_password` (cf. `compose.yaml`).
4. Avant le run : kill des processus `phpunit`/`artisan test` zombies dans le conteneur (`pkill -9 -f`) pour éviter la corruption par run précédent tué externalement.

**Hors worktree** (repo principal) : `make test` utilise toujours `testing` comme avant.

**Pourquoi pas SQLite in-memory** : le projet utilise des colonnes JSON (fulltext search Lunar) et des FK multi-table → non compatible SQLite.

**Pourquoi pas `paratest`** : l'user `weklo` ne peut pas créer les bases `testing_1`…`testing_N` nécessaires au parallélisme paratest — `SHOW GRANTS FOR 'weklo'@'%'` confirme grants uniquement sur `weklo` et `testing`.

### Note : segfault "signal 11" — DEUX causes distinctes

**Piste xdebug = faux départ** (commune aux deux) : `docker compose exec app php -m` confirme que ni xdebug ni pcov ne sont chargés, et `XDEBUG_MODE` n'est pas défini → `XDEBUG_MODE=off` n'apporte rien. Idem JIT : `opcache.enable_cli = Off` → le JIT ne tourne pas en CLI.

**Cause 1 — concurrence (résolue par l'isolation de base ci-dessus)** : deux runs concurrents sur la même base `testing` → deadlock `DROP TABLE` → connexion zombie → PHP killé mid-query par MySQL → signal 11 apparent.

**Cause 2 — accumulation cumulative dans un unique process PHP (résolue par le chunking, cf. section suivante)** : même sur base isolée (hors concurrence), `make test` plantait encore ~80% des runs complets — signal 11 (exit 139) OU hang (exit 124), en alternance, vers ~80% de la suite (zone `TreeManagerTest`/`LoyaltyManagerTest`/`OembedClientTest`/`ProductVideoManagerTest`). **Chaque test/fichier passe pourtant en isolation** (y compris les `*ModifierTest` avec mocks `Cart`, et `OembedClientTest` dont le HTTP est bien mocké) → le crash n'est ni un appel réseau réel, ni les mocks Mockery, ni la concurrence DB. C'est un effet purement **cumulatif** sur ~250+ tests dans le **même** process PHP : épuisement de la pile C / GC sur un graphe d'objets accumulé en fin de suite (`zend.max_allowed_stack_size = 0` → pas de protection stack-overflow sur la pile principale, segfault au lieu d'une `Error` catchable). Les pistes mémoire (`memory_limit = 512M`, déjà confortable) et extension C (redis/soap/pcntl) ont été écartées.

---


## Découpage de `make test` en chunks (anti-segfault cumulatif)

**Problème** : voir « Cause 2 » ci-dessus — un unique process PHP exécutant les 316 tests accumule de l'état jusqu'au crash (~80% des runs).

**Solution** : `make test` ne lance plus `php artisan test` en un seul process. Le script `scripts/run-tests-chunked.sh` découpe la suite en **plusieurs processus PHP successifs** (un *chunk* par dossier de tests). Chaque chunk = un `php artisan test <path>` neuf → l'état est borné par process, bien en-dessous du seuil de crash, et la suite redevient déterministe.

**Chunks** (dynamiques, tout nouveau sous-dossier de `tests/Feature/` est pris en charge automatiquement) :
1. `tests/Unit` (un seul chunk — tests légers, pas d'accumulation problématique)
2. un chunk par sous-dossier de `tests/Feature/*/` (chacun ≤ ~46 tests)
3. un chunk pour les fichiers `tests/Feature/*Test.php` à la racine

Les chunks tournent **en série** et partagent la même base de test (un seul à la fois) → aucune isolation DB supplémentaire requise (orthogonal à l'isolation par worktree). `RefreshDatabase` re-migre la base au démarrage de chaque chunk ; surcoût mesuré modeste (chunk Feature/AiImporter complet : ~25s migration incluse).

**Pourquoi pas `--process-isolation`** : un process PHP par *test* éliminerait aussi l'accumulation mais multiplie le temps par 3-5× ; avec des tests Feature déjà à ~15-18s pièce (seed complet en `setUp`), c'est rédhibitoire. Le chunking par dossier offre le même bénéfice anti-accumulation pour un surcoût négligeable.

**Pourquoi pas `paratest`** : non installé, et l'user `weklo` ne peut pas créer les bases `testing_1`…`testing_N` du parallélisme paratest (cf. ci-dessus). Le chunking série évite cette dépendance.

**Validé** : 3 runs complets `make test` consécutifs verts (316 tests), aucun signal 11 ni hang.

---


## Garde anti-wipe DB depuis les worktrees PKOS

**Problème** : `compose.yaml` fige `container_name: weklo-*`. Quand un agent PKOS lance `docker compose` depuis un worktree (`~/.pkos/worktrees/<id>/`), il n'a **pas** son propre conteneur isolé : il retombe sur le conteneur principal, donc sur la **base de dev `weklo`** de Rom. Une commande destructive (`make fresh`, `make install`, ou `make artisan CMD='migrate:fresh'`) vide alors la vraie base de dev (staff, produits, configs). Incident constaté le 2026-06-02.

**Garde — défense en profondeur** (zéro impact hors worktree) :

1. **Makefile (hôte)** : `WORKTREE_GUARD := $(findstring /.pkos/worktrees/,$(CURDIR))` détecte le worktree. Les cibles `fresh`, `install` et `lunar` font un fast-fail (`exit 1`) avec message explicite si lancées depuis un worktree.
2. **Flag conteneur** : en worktree, le Makefile injecte `-e PKOS_WORKTREE=1` dans `docker compose exec` (`WT_ENV`).
3. **Framework (`AppServiceProvider::boot`)** : `DB::prohibitDestructiveCommands()` bloque `migrate:fresh`, `migrate:refresh`, `migrate:reset` et `db:wipe` même via `make artisan`, dès que `PKOS_WORKTREE` est présent **et** que l'environnement n'est **pas** `testing`. Couvre le cas où on contourne les cibles Make. L'exclusion de `testing` est nécessaire : `RefreshDatabase` lance `migrate:fresh` sur la base `testing` (forcée par `phpunit.xml`) — la prohiber casserait `make test`.

**Conséquence** : depuis un worktree, le **seul** moyen autorisé de valider une migration est **`make test`** — la suite tourne sur la base `testing` (forcée par `phpunit.xml`, trait `RefreshDatabase`), jamais sur la base de dev. Ne jamais lancer `make fresh` / `migrate:fresh` pour tester un schéma ; si un reset réel de la base de dev est nécessaire, le faire hors worktree depuis le repo principal.

---


## Conventions Git

- Branches : `main` (prod), `develop` (intégration), `feature/*` par module
- **Conventional Commits** (`feat:`, `fix:`, `refactor:`, `chore:`, `docs:`…)
- **Pas de mention IA** dans les messages de commit (pas de `Co-Authored-By: Claude`, pas d'emoji generator, pas de référence à Anthropic)

---


## Outils IA — MCP servers projet

### 12.1 Décision

Le projet embarque **deux serveurs MCP (Model Context Protocol)** déclarés dans `.mcp.json` à la racine, accessibles par tout agent IA qui ouvre le dossier (Claude Code, Junie, etc.). Leur usage est **obligatoire** pour tout travail impliquant Laravel, Filament ou Lunar — voir `CLAUDE.md` §2 pour les règles comportementales.

### 12.2 Serveurs configurés

| Serveur | Package / Endpoint | Transport | Couverture |
|---|---|---|---|
| **`laravel-boost`** | `laravel/boost` v2.4 (dev) | stdio via `docker compose exec -T -u sail app php artisan boost:mcp` | Laravel 11, Filament 3, Livewire 3, PHP 8.3, Pint, Pest, Tailwind, schéma DB live, logs, tinker, routes, application-info |
| **`lunar-docs`** | `https://docs.lunarphp.com/mcp` | HTTP streamable | Doc officielle Lunar v1.x (`search_lunar_php`, `query_docs_filesystem`) |

### 12.3 Installation et configuration

**laravel-boost** :

```bash
make composer CMD='require laravel/boost --dev'
make artisan CMD='boost:install --mcp --no-interaction'
```

Le flag `--mcp` installe **uniquement** la config MCP dans `.mcp.json` — **pas** les guidelines (`--guidelines`) ni les skills (`--skills`), pour préserver le `CLAUDE.md` projet.

**Correction post-install** : `boost:install` génère une commande `vendor/bin/sail` qui ne s'applique pas à notre stack custom. Le `.mcp.json` est corrigé manuellement pour utiliser `docker compose exec -T -u sail app …`.

**lunar-docs** : déclaration HTTP directe dans `.mcp.json`, pas d'installation locale.

### 12.4 `.mcp.json` de référence

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "docker",
            "args": ["compose", "exec", "-T", "-u", "sail", "app", "php", "artisan", "boost:mcp"]
        },
        "lunar-docs": {
            "type": "http",
            "url": "https://docs.lunarphp.com/mcp"
        }
    }
}
```

### 12.5 Pourquoi pas Context7 uniquement

Context7 reste le fallback global (configuré au niveau utilisateur, pas projet), mais :

- Il n'est pas versionné par package installé — il peut servir la doc Lunar v2.x alors qu'on tourne en v1.x
- Il n'a pas accès au schéma DB local, aux logs, aux routes, au tinker
- Il ne résout pas les helpers projet

→ laravel-boost + lunar-docs sont **prioritaires**. Context7 est réservé aux packages tiers non couverts.

### 12.6 Fichier `boost.json`

Après un `boost:install` complet (avec guidelines), un `boost.json` est généré à la racine pour configurer quels packages Boost introspecte. Non utilisé ici puisqu'on n'installe que le flag `--mcp`.

---


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


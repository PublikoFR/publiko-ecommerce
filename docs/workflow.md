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

- **JAMAIS `truncate()` dans un seeder** : `TRUNCATE` est du DDL → **COMMIT implicite** en MySQL. Quand un seeder tourne dans le `setUp()` d'un test `RefreshDatabase` (`$this->seed(DatabaseSeeder::class)`), le TRUNCATE committe la transaction d'isolation → les données de test ne sont plus rollback → elles s'accumulent entre les tests d'un même process → collisions d'unicité (`Duplicate entry … for key …_unique`) sur les tests suivants. Pire, l'erreur réelle est **masquée** par un `SQLSTATE[42000] 1305 SAVEPOINT trans2 does not exist` (le rollback du savepoint échoue car le COMMIT l'a libéré). Toujours utiliser `->delete()` (DML) pour purger un modèle dans un seeder idempotent.

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


## Deadlocks MySQL (SQLSTATE 1213) en run chunké — fuite de transaction `RefreshDatabase`

**Symptôme** (session du 2026-07-20) : le chunk `tests/Feature/Shipping/` échouait avec ~39 tests rouges, en majorité des `DeadlockException` (SQLSTATE 1213), plus quelques `ViewException` et `TypeError`. **Le même chunk lancé en isolation était 100% vert.** D'où l'hypothèse initiale — fausse — d'une contention DB entre chunks, imputée aux row/gap locks du `delete()` introduit dans `PkoStorefrontCmsSeeder` (commit `b3bf3a3`, cf. § « JAMAIS `truncate()` dans un seeder »).

**Cause réelle** : les deadlocks sont un **symptôme**, pas la maladie. Quand une exception est levée pendant un test, la transaction ouverte par `RefreshDatabase` peut ne pas être rollback ; les verrous restent tenus, et le test suivant — ou le chunk suivant — deadlocke dessus. En isolation le premier test échoue silencieusement dans le bruit et la cascade ne se voit pas ; en run chunké elle se propage. **Chercher toujours la première exception, jamais le premier deadlock.**

Trois sources d'exception ont été corrigées :

1. **Morph map Lunar.** Lunar enregistre une morph map (`ModelManifest::morphMap()`) : la colonne `purchasable_type` contient l'alias `product_variant`, **jamais** le FQCN. Comparer à `ProductVariant::class` ne matche aucune ligne. Toujours passer par `(new ProductVariant)->getMorphClass()` — dans le code applicatif comme dans les fixtures de test qui insèrent en `DB::table()`.
2. **Stripe appelé au rendu.** Le composant Livewire `stripe.payment` (`Lunar\Stripe\Components\PaymentForm`) appelle `Stripe::createIntent()` **dès le rendu**. Sans clé d'API → `ViewException` ; avec une clé factice → tout le SDK se déroule (segfault intermittent observé). Le SDK Stripe utilise son propre client cURL : ni `Http::fake()` ni `Http::preventStrayRequests()` ne le couvrent. Parade : `Stripe::fake()` dans `Tests\TestCase::setUp()` (substitue un `MockClient`), plus un stub Livewire inerte (`Tests\Stubs\FakeStripePaymentForm`) pour les tests qui rendent la page de checkout.
3. **`Mockery::close()` appelé à la main.** Plusieurs `tearDown()` maison appelaient `Mockery::close()` puis `parent::tearDown()`, court-circuitant le teardown du framework. Ne jamais le faire : Laravel s'en charge dans le bon ordre.

À quoi s'ajoute une dépendance à l'ordre d'exécution : `FreeShippingModifierTest` s'appuyait sur une `TaxClass` laissée en base par un autre test au lieu de la créer lui-même sous `RefreshDatabase`.

**Validé** : plus aucune `DeadlockException` sur 3 runs chunkés complets, là où le chunk `Shipping` produisait ~39 échecs. Le chunk passe à 66 tests verts quand il n'est pas interrompu par le segfault aléatoire décrit ci-dessous — lequel est un problème **distinct**, préexistant, et non résolu par ce fix.

### Segfault "signal 11" aléatoire — l'explication cumulative ne tient plus

Mesures du 2026-07-22 (3 runs chunkés complets + 1 run à raison d'un conteneur Docker par chunk) : le signal 11 frappe **2 à 4 chunks par run, jamais les mêmes**, et **après** que tous les tests du chunk sont passés (le résumé `Tests:` n'est simplement jamais imprimé).

Cela contredit la « Cause 2 » documentée plus haut (accumulation au-delà de ~250 tests dans un process) : les chunks touchés font 13 à 66 tests. Deux hypothèses écartées par la mesure :
- *chunk devenu trop gros* → non : `Unit` (218 tests) passe dans les runs où `Filament` (13 tests) segfaulte ;
- *état partagé par le conteneur enchaînant les 13 chunks* → non : le découpage en un conteneur Docker par chunk segfaulte autant (4 chunks).

#### Diagnostic (investigation du 2026-07-25) — dépassement de pile C au shutdown

Le signal 11 survient **au shutdown du process PHP**, une fois tous les tests du chunk
passés mais **avant** l'impression du résumé `Tests:`. À ce moment PHP déroule la
destruction du graphe d'objets accumulé (container Laravel, composants Livewire,
cart/modifiers Lunar, resources Filament) : une **récursion C** (`__destruct` en chaîne,
puis nettoyage `zend_objects_store` + `gc_collect_cycles`). Cette récursion s'exécute sur
la **pile principale du process**, plafonnée par défaut à **8 Mo** (`ulimit -s 8192`).
Quand la profondeur du graphe dépasse ce plafond → `SIGSEGV` (exit 139). C'est le
seul mécanisme qui explique **tous** les faits observés :
- **exit 139 (SIGSEGV), pas 137 (OOM)** : une pression mémoire tuerait par OOM-kill (137),
  pas par segfault. L'hôte avait d'ailleurs ~29 Go libres à la reproduction ;
- **au shutdown, jamais pendant un test** : la destruction du graphe n'a lieu qu'à la fin ;
- **chunks aléatoires, jamais les mêmes** : la profondeur atteinte dépend du layout heap
  (ASLR, fragmentation), non-déterministe d'un run à l'autre — pas d'un chunk « trop gros ».

**Non reproductible à froid** : 40 runs `Filament` isolés + 40 runs en concurrence sur la
base `testing` partagée, le 2026-07-25 sur un hôte peu chargé (29 Go libres) → **zéro
crash**. Le déclenchement dépend des conditions hôte au moment du run du 2026-07-22
(charge/fragmentation), pas d'un bug métier reproductible. On ne poursuit pas la chasse à
un crash aléatoire non reproductible (discipline anti-exploration-sans-fin) : on borne le
risque à sa racine.

**Parade appliquée** (préventive — élargit la ressource dont l'épuisement produit le SIGSEGV) :
1. `scripts/run-tests-chunked.sh` relève `ulimit -s` à **65536** (64 Mo) avant chaque chunk :
   8× de marge sur la pile C → la récursion de destruction ne peut plus l'atteindre en
   pratique.
2. `docker/app/php.ini` active `zend.max_allowed_stack_size = -1` (auto-détecté depuis
   `RLIMIT_STACK`, donc SAPI-safe : ~8 Mo en apache, ~64 Mo en CLI de test). Si un
   dépassement **userland** survenait encore, il devient une `\Error` **catchable avec
   stack trace** au lieu d'un segfault muet → tout futur cas sera diagnosticable.

**Limite honnête** : n'ayant pas pu reproduire le crash à froid, la parade n'est pas
*prouvée* contre une occurrence vivante. Elle cible directement le mécanisme le plus
probable (SIGSEGV = pile épuisée) et est sans risque. Le garde `zend.max_allowed_stack_size`
n'intercepte que la récursion *userland* (appels VM `__destruct`) ; la récursion *C* pure de
l'engine (cleanup `zend_objects_store`) n'est couverte que par l'élargissement `ulimit -s`.

**Méthode de repro** (un `make test` complet prend ~30 min — ne pas itérer dessus) : lancer le chunk suspect en isolation *et* dans l'enchaînement chunké, puis comparer. Un chunk vert isolé et rouge en chaîne = fuite d'état, pas bug métier. Si un segfault réapparaît malgré la parade, chercher d'abord une `\Error: Maximum call stack size` dans la sortie (grâce à `zend.max_allowed_stack_size`) — sa stack trace pointera la récursion fautive.

---


## Garde anti-wipe DB (dev / local)

**Problème** : `compose.yaml` fige `container_name: weklo-*`. Un `migrate:fresh` / `migrate:refresh` / `migrate:reset` / `db:wipe` lancé — par un agent PKOS, un `make artisan` brut, ou par accident — retombe sur le conteneur principal et **vide la base de dev `weklo`** (staff, produits, configs). Incidents constatés les 2026-06-02 et 2026-07-16. Le premier garde (limité au flag `PKOS_WORKTREE`) laissait passer tout `php artisan migrate:fresh` lancé **hors worktree**, dans le conteneur principal — d'où le second wipe.

**Garde — défense en profondeur** :

1. **Framework (`AppServiceProvider::boot`) — garde principal, couvre TOUS les modes d'invocation** :
   ```php
   DB::prohibitDestructiveCommands(
       $this->app->environment('production')
           || (! $this->app->environment('testing')
               && ! filter_var(env('ALLOW_DB_WIPE', false), FILTER_VALIDATE_BOOLEAN))
   );
   ```
   Bloque `migrate:fresh` / `migrate:refresh` / `migrate:reset` / `db:wipe` (le flag `FreshCommand::$prohibitedFromRunning`, vérifié avant tout accès DB). Matrice :
   | Contexte | Destructif autorisé ? |
   |---|---|
   | `production` | ❌ jamais |
   | `testing` (bases `testing_*`, forcé par `phpunit.xml`) | ✅ — `RefreshDatabase` en a besoin, ne pas casser `make test` |
   | `local` / dev **sans** flag | ❌ bloqué (agent, artisan brut, accident) |
   | `local` / dev **avec** `ALLOW_DB_WIPE=1` | ✅ bypass explicite, réservé à `make fresh` |
2. **Bypass sanctionné** : seule la cible `make fresh` passe `ALLOW_DB_WIPE=1` (`$(EXEC) sh -c 'ALLOW_DB_WIPE=1 php artisan migrate:fresh --force'`). C'est le **seul** chemin autorisé pour reset la base de dev, et il est déclenché explicitement par l'humain.
3. **Makefile (worktree)** : `WORKTREE_GUARD` fait toujours un fast-fail sur `fresh`/`install`/`lunar` depuis un worktree (garde redondant, message clair).

**Règles** :
- **Un agent PKOS ne lance JAMAIS `migrate:fresh` / `db:wipe` sur la base dev.** Pour valider une migration → **`make test`** (base `testing`, jamais la dev).
- Reset réel de la dev → `make fresh` (humain), qui porte le bypass. Ne jamais ajouter `ALLOW_DB_WIPE=1` à la main dans une commande d'agent.

---


## File d'attente (queue) et scheduler

Un **seul cron** est nécessaire par environnement, le cron Laravel standard :

```cron
* * * * * /usr/local/bin/php /home/<user>/public_html/weklo/<env>/artisan schedule:run >> /dev/null 2>&1
```

Tout le reste (import IA, polling transporteurs, polling Pennylane, worker de
queue) est déclaré dans `routes/console.php` et piloté par ce tick — ne jamais
ajouter de ligne de crontab par tâche.

### Worker

L'hébergement O2switch est mutualisé : ni Supervisor ni systemd pour maintenir un
`queue:work` résident. Le worker est donc déclaré dans le scheduler et vide la
file une fois par minute avant de s'arrêter :

```php
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()->withoutOverlapping()->runInBackground();
```

`--max-time=55` garde le processus sous la minute pour ne pas chevaucher le tick
suivant ; `withoutOverlapping()` couvre le cas résiduel.

### `QUEUE_CONNECTION` — implications

`sync` (valeur actuelle sur dev) **n'est pas une file d'attente** : chaque
`dispatch()` s'exécute dans le processus de la requête HTTP courante. Donc :

- la latence du job s'ajoute à celle de la page ;
- une exception dans le job **fait échouer la requête** — c'est ainsi qu'un 400 de
  l'API Pennylane est devenu une 500 de checkout *et* une 500 de webhook Stripe,
  cf. `docs/packages/pennylane.md` et `docs/payments.md` §4.3 ;
- `$tries` / `backoff` sont ignorés et `failed_jobs` reste vide.

Les jobs concernés ne sont pas anodins : Pennylane (facture, avoir),
`CreateCarrierShipmentJob` (SOAP transporteur), et l'import IA
(`ParseFileToStagingJob`, `ImportStagingToLunarJob`) dont les appels LLM
dépassent largement le timeout HTTP.

**Ordre de bascule vers `database` — impératif :** déployer d'abord le worker,
*puis* basculer `QUEUE_CONNECTION` dans le `.env` du serveur (+ `config:clear`).
L'inverse empile les jobs dans la table `jobs` sans jamais les exécuter, sans
aucune erreur visible : plus de facture, plus de mail, plus d'étiquette. Les
tables `jobs` / `failed_jobs` / `job_batches` existent déjà.

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


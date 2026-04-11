# CLAUDE.md — Instructions MDE Distribution

## Contexte projet

Back-office **Laravel 11 + Lunar 1.x + Filament 3** remplaçant PrestaShop 8 pour **MDE Distribution** (distributeur B2B matériaux de construction, domotique, portails, volets, automatismes). Ce fichier contient uniquement les **instructions**. Toute la documentation technique (stack, choix, architecture, packages, shipping, paiements, gotchas Lunar…) vit dans `docs/`.

**Ne consulte `docs/` que dans deux cas** :
1. Quand tu prépares un **Plan Mode** ou un plan d'implémentation pour une demande non triviale
2. Quand tu travailles sur une **grosse feature** (plusieurs fichiers, plusieurs packages, ou impact architectural)

Pour toute tâche courte/ciblée (bug fix, petit ajout, question directe), **ne charge pas** `docs/technical-choices.md` — réponds ou code directement à partir du contexte de la conversation et du code du projet. La règle de **mise à jour** de la doc au commit (§1) s'applique toujours, elle.

---

## 1. Documentation projet — où chercher, quand écrire

### Fichiers de référence

| Fichier | Rôle |
|---|---|
| `docs/technical-choices.md` | **Référence maître** : stack, mécanismes d'extension Lunar, paiements, shipping, RBAC, points d'attention Lunar, tests, arborescence, décisions tranchées |
| `cahier-des-charges-mde-laravel.md` (racine) | Cahier des charges contractuel MDE |
| `CLAUDE.md` (ce fichier) | Instructions comportementales pour toi uniquement — jamais de choix techniques ici |

### Règle de maintenance documentaire — OBLIGATOIRE

**Avant chaque commit**, si ton changement introduit **l'un** des éléments suivants, tu **DOIS** mettre à jour `docs/technical-choices.md` (ou créer un nouveau fichier thématique dans `docs/` si le sujet mérite son propre document) **dans le même commit** :

- Une nouvelle décision technique ou un arbitrage non trivial
- Une nouvelle dépendance Composer ou NPM
- Une nouvelle variable d'environnement
- Une nouvelle table ou migration structurante
- Un nouveau package MDE sous `packages/mde/*`
- Une nouvelle règle de codage ou de workflow
- Un nouveau driver (paiement, shipping, search, etc.)
- L'installation ou la configuration d'un MCP server
- Le rejet documenté d'une alternative technique (pourquoi pas X)

La doc est partie intégrante du deliverable. Un commit qui introduit une décision sans mettre à jour la doc est considéré incomplet.

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

1. **Jamais modifier `vendor/`** — aucun patch, aucune exception. Toute personnalisation passe par les mécanismes d'extension documentés dans `docs/technical-choices.md`.
2. **`declare(strict_types=1);`** en tête de chaque fichier PHP que tu crées ou touches.
3. **PSR-12** — lance `make lint` avant chaque commit. Si rouge → corrige avant de committer.
4. **Migrations MDE** : préfixe de table `mde_`, dans `database/migrations/` (ou `packages/mde/<module>/database/migrations/` si spécifique à un module packagé).
5. **Modules métier** : dans `packages/mde/*`, enregistrés comme Filament Plugin, **jamais** en modifiant le core Lunar ni les resources Lunar Admin.
6. **Prix en cents** — toujours des entiers. `19900` = 199,00 €. Jamais de float pour les montants.
7. **`attribute_data` Lunar** — toujours une collection de `Lunar\FieldTypes\*` (`Text`, `TranslatedText`, `Number`, `Dropdown`…), jamais de strings bruts.
8. **Laravel Cashier interdit** pour encaisser une commande Lunar. Réservé à d'éventuels abonnements MDE dédiés.
9. **Resources Filament custom interdites en phase 1** pour les entités déjà couvertes par Lunar Admin (produits, variantes, collections, prix, commandes, clients, taxes, promos, livraison, marques, tags, canaux, devises, staff). Étendre via `LunarPanel::extensions()` / `ResourceExtension` à la place.
10. **Policies Shield** — régénérées automatiquement par `make install`. Ne pas éditer à la main, sauf override explicite documenté dans `docs/`.
11. **Service Docker** = `app` (pas `laravel.test`, pas `sail`). Stack custom Traefik + phpMyAdmin.

---

## 4. Workflow de commit

1. **Avant de committer** :
   - `make test` doit être vert
   - `make lint` doit être vert
   - `docs/technical-choices.md` mis à jour si le commit introduit une décision/dépendance/env var/table/règle (voir §1)

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

- Modifier `vendor/` — **jamais**
- Modifier les migrations Lunar publiées — créer une migration MDE dédiée qui ajoute les colonnes via `Schema::table()`
- Toucher `config/lunar/*.php` sans raison forte (facilite les mises à jour)
- Créer une Filament Resource pour une entité déjà couverte par Lunar
- Utiliser Cashier pour encaisser
- Répondre de mémoire sur Laravel/Filament/Livewire/Lunar sans avoir interrogé les MCPs
- Committer du code qui introduit une décision technique sans mettre à jour `docs/`
- Mentionner Claude/Anthropic dans un commit

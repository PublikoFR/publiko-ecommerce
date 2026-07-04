# Tests E2E — Infrastructure Playwright jetable

## Stack

- **Playwright** (`@playwright/test` v1.61+) — runner, chromium uniquement
- **Docker Compose** — stack E2E isolée (app + mysql + redis) par run
- Fichier de référence : `docker-compose.e2e.yml`

## Isolation par run

Chaque run reçoit un `COMPOSE_PROJECT_NAME = weklo-e2e-<port>`, ce qui isole
complètement les ressources Docker (réseau, volumes, conteneurs). Plusieurs
worktrees PKOS peuvent lancer leurs tests E2E en parallèle sans collision.

## Variables d'environnement

| Var | Rôle | Défaut |
|---|---|---|
| `E2E_PORT` | Port hôte de l'app E2E | Aléatoire (18000–19000, via `e2e-run.sh`) |
| `E2E_MAIN_REPO` | Chemin du repo principal (vendor, build) | Détecté via `git worktree list` |
| `E2E_APP_KEY` | Laravel APP_KEY | Lu depuis `.env` du repo principal |

## Commandes

```bash
npm install                  # première fois
npx playwright install chromium  # première fois

npm run test:e2e             # run complet (port aléatoire)
npm run test:e2e:smoke       # smoke tests uniquement
npm run test:e2e:ui          # interface graphique Playwright
```

## Lifecycle (global-setup.ts)

1. `docker compose up -d --wait` — lève app + mysql + redis
2. Écriture `e2e/.e2e-state.json` (permet teardown même si la suite suivante échoue)
3. Attente HTTP de l'app (`waitForApp`)
4. Attente connexion MySQL avec le user E2E (`waitForDb`)
5. Init des volumes `storage/` et `bootstrap/cache/` (droits sail)
6. `php artisan migrate:fresh --seed --force` — charge tous les Pko*Seeders
7. `php artisan shield:generate` (en root, puis chown sail) + `shield:super-admin`
8. `php artisan optimize:clear`

## Lifecycle (global-teardown.ts)

`docker compose down -v --remove-orphans` — détruit conteneurs + volumes nommés.

## Ajout d'un test de parcours

Créer `e2e/tests/<feature>.spec.ts`. Utiliser des chemins relatifs :
`page.goto('/catalogue')`, `page.goto('/admin/produits')`. Voir `e2e/README.md`.

## Seeders disponibles

Tous les seeders `database/seeders/Pko*Seeder.php` sont chargés :
produits, variantes, clients, commandes, shipping, taxes, channels, loyalty tiers,
CMS storefront, store settings, media, permissions IA/vidéos/CMS.

Si un parcours nécessite une fixture manquante (code promo, stock limité, compte pro) :
ajouter un seeder dédié dans `database/seeders/` et l'appeler depuis `DatabaseSeeder`.
Ne pas créer de migration pour ça — seeder uniquement.

## Décisions techniques

- **Pas de Traefik en E2E** : l'app est exposée directement sur un port hôte dynamique.
- **vendor/ et public/build/ montés depuis le repo principal** : les worktrees n'ont pas
  de vendor/ ni d'assets buildés.
- **Volumes Docker nommés** pour `storage/`, `bootstrap/cache/`, `mysql_data` :
  isolation complète, détruits en teardown.
- **shield:generate exécuté en root** dans le conteneur : les fichiers `app/Policies/`
  sont dans le worktree (bind-mount) ; root peut y écrire, ownership rétabli à sail
  immédiatement après.
- **Image utilisée** : `ecom-laravel-app` (construite par `make build` du stack dev).
  Si absente, `global-setup.ts` la construit automatiquement.

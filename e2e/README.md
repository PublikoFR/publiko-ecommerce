# Tests E2E — Weklo ecom-laravel

Infrastructure Playwright jetable basée sur Docker. Chaque run lève une stack isolée (MySQL + Redis + app), joue les migrations + seed, exécute les tests, puis détruit tout.

## Prérequis

- Docker + Docker Compose v2
- Node.js ≥ 18
- Image `ecom-laravel-app` construite (`make build` depuis le repo principal)
- `vendor/` et `public/build/` présents dans le repo principal

### Installation des dépendances (une seule fois)

```bash
npm install
npx playwright install chromium
```

## Lancer les tests

```bash
# Run complet (port aléatoire, isolation totale)
npm run test:e2e

# Seulement les smoke tests
npm run test:e2e:smoke

# Mode debug interactif (Playwright Inspector)
npm run test:e2e:debug

# Interface graphique Playwright UI
npm run test:e2e:ui
```

Le port E2E est sélectionné automatiquement dans la plage `18000–19000`. Pour le fixer :

```bash
E2E_PORT=18042 npm run test:e2e
```

## Variables d'environnement

| Variable | Rôle | Défaut |
|---|---|---|
| `E2E_PORT` | Port hôte pour l'app (ex: `18042`) | Aléatoire (18000–19000) |
| `E2E_MAIN_REPO` | Chemin absolu du repo principal | Détecté via `git worktree list` |
| `E2E_APP_KEY` | Laravel `APP_KEY` | Lu depuis `.env` du repo principal |

## Architecture

```
playwright.config.ts       # Config Playwright (baseURL, globalSetup/Teardown)
docker-compose.e2e.yml     # Stack isolée : app + mysql + redis (pas de Traefik)
e2e/
  global-setup.ts          # Lève la stack, migrate:fresh --seed, shield
  global-teardown.ts       # docker compose down -v (conteneurs + volumes)
  tests/
    smoke.spec.ts          # Tests de base : app répond, pas de 500
  .e2e-state.json          # Fichier d'état (généré, gitignore)
scripts/
  e2e-run.sh               # Wrapper : choisit le port, lance playwright
  e2e-up.sh                # Debug : lève la stack manuellement
  e2e-down.sh              # Debug : détruit la stack manuellement
```

## Écrire un nouveau test

Créer un fichier `e2e/tests/<feature>.spec.ts` :

```ts
import { test, expect } from '@playwright/test';

test('le produit X est visible', async ({ page }) => {
  await page.goto('/produits');
  await expect(page.getByText('Nom du produit')).toBeVisible();
});
```

La `baseURL` est déjà configurée dans `playwright.config.ts` — utiliser des chemins relatifs (`/produits`, `/panier`).

## Isolation multi-worktrees (runs parallèles)

Chaque worktree PKOS tourne avec un `E2E_PORT` différent → chaque run est totalement isolé (project compose `weklo-e2e-<port>`, volumes nommés par projet, pas de réseau partagé). Les stacks ne se voient pas.

Prérequis : chaque worktree a son propre repo git (c'est le cas par défaut avec `pkos worktree`). Le `E2E_MAIN_REPO` est détecté automatiquement via `git worktree list`.

## Debugging

### Stack non détruite après un run avorté

```bash
# Avec le port connu
E2E_PORT=18042 bash scripts/e2e-down.sh

# Ou lister tous les projets compose weklo-e2e-*
docker ps --filter "label=com.docker.compose.project" --format '{{.Label "com.docker.compose.project"}}' | sort -u | grep weklo-e2e
# Puis pour chaque projet
docker compose -f docker-compose.e2e.yml -p weklo-e2e-XXXX down --volumes
```

### Logs de la stack E2E

```bash
# Après un e2e-up.sh (debug)
docker compose -f docker-compose.e2e.yml -p weklo-e2e-18042 logs -f app
```

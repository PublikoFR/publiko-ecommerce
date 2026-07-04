#!/usr/bin/env bash
# Wrapper principal pour lancer les tests E2E Playwright.
# Sélectionne un port aléatoire pour isoler les runs parallèles (un par worktree).
# Usage : bash scripts/e2e-run.sh [options playwright]
# Ex :    bash scripts/e2e-run.sh --grep "smoke"

set -euo pipefail

# Sélectionner un port si non fourni
if [ -z "${E2E_PORT:-}" ]; then
  # Tentative via shuf (Linux), fallback node (cross-platform)
  if command -v shuf &>/dev/null; then
    E2E_PORT=$(shuf -i 18000-19000 -n 1)
  else
    E2E_PORT=$(node -e 'process.stdout.write(String(18000 + Math.floor(Math.random() * 1000)))')
  fi
fi
export E2E_PORT

echo "→ E2E_PORT=$E2E_PORT"
exec npx playwright test "$@"

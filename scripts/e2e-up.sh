#!/usr/bin/env bash
# Démarre manuellement la stack E2E (utile pour debugging).
# Définit E2E_PORT si absent, affiche l'URL de l'app.
# La teardown manuelle : bash scripts/e2e-down.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

if [ -z "${E2E_PORT:-}" ]; then
  E2E_PORT=$(shuf -i 18000-19000 -n 1 2>/dev/null || node -e 'process.stdout.write(String(18000 + Math.floor(Math.random() * 1000)))')
fi
export E2E_PORT

# Main repo = premier worktree listé par git
E2E_MAIN_REPO=$(git -C "$ROOT" worktree list --porcelain | grep '^worktree ' | head -1 | awk '{print $2}')
export E2E_MAIN_REPO

E2E_APP_KEY=$(grep '^APP_KEY=' "$E2E_MAIN_REPO/.env" | cut -d= -f2-)
export E2E_APP_KEY

PROJECT="weklo-e2e-${E2E_PORT}"
echo "→ Lancement stack E2E [project=$PROJECT, port=$E2E_PORT]"
echo "  main repo : $E2E_MAIN_REPO"

docker compose -f "$ROOT/docker-compose.e2e.yml" -p "$PROJECT" up -d --wait

echo ""
echo "Stack E2E prête → http://localhost:${E2E_PORT}"
echo "Pour lancer les migrations : bash scripts/e2e-seed.sh $PROJECT"
echo "Pour éteindre            : E2E_PORT=$E2E_PORT bash scripts/e2e-down.sh"
echo "PROJECT=$PROJECT (à réexporter pour les commandes manuelles)"

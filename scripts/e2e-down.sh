#!/usr/bin/env bash
# Détruit la stack E2E (conteneurs + volumes).
# Usage : E2E_PORT=<port> bash scripts/e2e-down.sh
# Ou avec le project name explicite : PROJECT=pko-e2e-18042 bash scripts/e2e-down.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Détecter le project name
if [ -n "${PROJECT:-}" ]; then
  E2E_PROJECT="$PROJECT"
elif [ -n "${E2E_PORT:-}" ]; then
  E2E_PROJECT="pko-e2e-${E2E_PORT}"
elif [ -f "$ROOT/e2e/.e2e-state.json" ]; then
  E2E_PROJECT=$(node -e "const s=require('$ROOT/e2e/.e2e-state.json'); process.stdout.write(s.projectName)")
else
  echo "⛔ Impossible de déterminer le project name."
  echo "   Définir PROJECT= ou E2E_PORT= ou lancer après un 'npm run test:e2e'."
  exit 1
fi

echo "→ Teardown stack E2E [project=$E2E_PROJECT]"
docker compose -f "$ROOT/docker-compose.e2e.yml" -p "$E2E_PROJECT" down --volumes --remove-orphans
rm -f "$ROOT/e2e/.e2e-state.json"
echo "  Stack détruite."

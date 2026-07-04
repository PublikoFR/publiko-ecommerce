#!/usr/bin/env bash
# Joue migrate:fresh --seed sur une stack E2E déjà levée (debug).
# Usage : bash scripts/e2e-seed.sh <project-name>
# Ex :    bash scripts/e2e-seed.sh pko-e2e-18042
#
# Le project-name est affiché par e2e-up.sh au lancement.

set -euo pipefail

if [ -z "${1:-}" ]; then
  echo "⛔ Usage : bash scripts/e2e-seed.sh <project-name>"
  echo "   Ex :    bash scripts/e2e-seed.sh pko-e2e-18042"
  exit 1
fi

PROJECT="$1"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="$ROOT/docker-compose.e2e.yml"

echo "→ migrate:fresh --seed [project=$PROJECT]"
docker compose -f "$COMPOSE_FILE" -p "$PROJECT" \
  exec -T -u sail app \
  php artisan migrate:fresh --seed --force --no-interaction

echo "  Seed terminé."

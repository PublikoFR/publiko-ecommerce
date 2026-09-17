#!/usr/bin/env bash
#
# Déploiement Weklo → O2switch mutualisé (PROD / DEV) — modèle RSYNC d'artefacts.
#
# Usage : bash deploy/deploy.sh deploy/prod.env
#         DRY_RUN=true bash deploy/deploy.sh deploy/prod.env
#
# Pourquoi rsync et pas "git pull + composer côté serveur" ?
#   Le mutualisé O2switch (cPanel) peut exécuter composer/git, mais on ne veut
#   AUCUNE dépendance de build côté serveur (versions PHP CLI variables, pas de
#   remote git garanti). On build EN LOCAL (composer + npm) et on POUSSE le
#   résultat (vendor/ + public/build) par rsync. Le serveur ne fait plus que
#   migrer + (re)construire les caches Laravel via un binaire php CLI dédié.
#
# Le fichier env passé en argument fournit la config (voir deploy/*.env.example).
#
set -euo pipefail

# --- couleurs / logs -------------------------------------------------------
if [[ -t 1 ]]; then
  C_OK=$'\033[0;32m'; C_INFO=$'\033[0;36m'; C_WARN=$'\033[0;33m'; C_ERR=$'\033[0;31m'; C_RST=$'\033[0m'
else
  C_OK=''; C_INFO=''; C_WARN=''; C_ERR=''; C_RST=''
fi
log()   { echo "${C_INFO}==>${C_RST} $*"; }
ok()    { echo "${C_OK}  ✓${C_RST} $*"; }
warn()  { echo "${C_WARN}  !${C_RST} $*"; }
die()   { echo "${C_ERR}✗ $*${C_RST}" >&2; exit 1; }

# --- emplacement racine du projet (le script vit dans deploy/) -------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

# --- argument : fichier env ------------------------------------------------
ENV_FILE="${1:-}"
[[ -n "$ENV_FILE" ]] || die "Usage : bash deploy/deploy.sh <fichier.env>  (ex: deploy/prod.env)"
[[ -f "$ENV_FILE" ]] || die "Fichier env introuvable : $ENV_FILE  (copier le .example et remplir)"

log "Chargement de la config : $ENV_FILE"
# La variable passée en préfixe (ex: DRY_RUN=true bash deploy/deploy.sh …) DOIT
# primer sur la valeur du fichier env, sinon `source` l'écrase silencieusement
# et un « dry-run » devient un déploiement réel. On la capture AVANT le source.
_DRY_RUN_CLI="${DRY_RUN:-}"
# shellcheck disable=SC1090
set -a; source "$ENV_FILE"; set +a

# --- valeurs par défaut ----------------------------------------------------
SSH_PORT="${SSH_PORT:-22}"
SSH_KEY="${SSH_KEY:-}"
REMOTE_PHP="${REMOTE_PHP:-php}"
# Priorité : variable CLI (préfixe) > valeur du fichier env > défaut false.
DRY_RUN="${_DRY_RUN_CLI:-${DRY_RUN:-false}}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"

# --- validation des obligatoires -------------------------------------------
[[ -n "${SSH_HOST:-}" ]]    || die "SSH_HOST manquant dans $ENV_FILE"
[[ -n "${SSH_USER:-}" ]]    || die "SSH_USER manquant dans $ENV_FILE"
[[ -n "${REMOTE_PATH:-}" ]] || die "REMOTE_PATH manquant dans $ENV_FILE"

# --- expansion ~ pour SSH_KEY ----------------------------------------------
if [[ -n "$SSH_KEY" ]]; then
  SSH_KEY="${SSH_KEY/#\~/$HOME}"
fi

# --- commandes de build (paramétrables via le fichier env) -----------------
# BUILD_COMPOSER / BUILD_NPM permettent de builder dans le conteneur Docker
# (PHP = version serveur) plutôt que sur l'hôte, indispensable si l'hôte a un
# PHP plus ancien qu'une contrainte du composer.lock. Défaut : binaires hôte.
# Ex (ce projet) : BUILD_COMPOSER="docker compose -p ecom-laravel exec -T -u sail app composer"
BUILD_COMPOSER="${BUILD_COMPOSER:-composer}"
BUILD_NPM="${BUILD_NPM:-npm}"

# --- multiplexage SSH (ControlMaster) --------------------------------------
# On ouvre UNE connexion maître persistante que TOUT réutilise (rsync + chaque
# ssh distant) → une seule authentification, pas de rafale de connexions.
CTRL_SOCK="${TMPDIR:-/tmp}/o2switch-deploy-$$.sock"
MUX_OPTS=(-o ControlMaster=auto -o ControlPath="$CTRL_SOCK" -o ControlPersist=300)

# --- construction de la commande ssh ---------------------------------------
# IdentitiesOnly=yes : ne présente QUE la clé fournie (évite que l'agent
# gnome-keyring propose toutes ses clés et sature l'auth → "Too many auth failures").
SSH_OPTS=(-p "$SSH_PORT" -o IdentitiesOnly=yes "${MUX_OPTS[@]}")
if [[ -n "$SSH_KEY" ]]; then
  [[ -f "$SSH_KEY" ]] || warn "SSH_KEY défini mais introuvable : $SSH_KEY"
  SSH_OPTS+=(-i "$SSH_KEY")
fi
SSH_TARGET="${SSH_USER}@${SSH_HOST}"
# chaîne -e pour rsync (citée) — mêmes options + MÊME ControlPath : rsync
# réutilise le tunnel maître au lieu d'ouvrir une nouvelle auth.
SSH_CMD="ssh -p ${SSH_PORT} -o IdentitiesOnly=yes -o ControlMaster=auto -o ControlPath=${CTRL_SOCK} -o ControlPersist=300"
[[ -n "$SSH_KEY" ]] && SSH_CMD+=" -i ${SSH_KEY}"

IS_DRY=false
[[ "$DRY_RUN" == "true" ]] && IS_DRY=true

echo
log "Cible       : ${SSH_TARGET}:${REMOTE_PATH} (port ${SSH_PORT})"
log "PHP distant : ${REMOTE_PHP}"
log "Migrations  : ${RUN_MIGRATIONS}"
$IS_DRY && warn "MODE DRY-RUN : aucune commande distante ne sera exécutée."
echo

# --- helpers d'exécution (respectent DRY_RUN) ------------------------------
run_local() {
  if $IS_DRY; then
    echo "${C_WARN}[DRY-RUN]${C_RST} (local) $*"
  else
    "$@"
  fi
}
run_remote() {
  local cmd="$1"
  if $IS_DRY; then
    echo "${C_WARN}[DRY-RUN]${C_RST} ssh ${SSH_OPTS[*]} ${SSH_TARGET} -- \"cd ${REMOTE_PATH} && ${cmd}\""
  else
    ssh "${SSH_OPTS[@]}" "$SSH_TARGET" -- "cd $(printf '%q' "$REMOTE_PATH") && set -e && ${cmd}"
  fi
}

# --- connexion maître multiplexée ------------------------------------------
close_master() {
  if [[ -S "$CTRL_SOCK" ]]; then
    ssh -o ControlPath="$CTRL_SOCK" -O exit "$SSH_TARGET" >/dev/null 2>&1 || true
  fi
  rm -f "$CTRL_SOCK" 2>/dev/null || true
}
trap close_master EXIT

_try_master() {
  ssh "${SSH_OPTS[@]}" -o ConnectTimeout=15 -N -f "$SSH_TARGET" >/dev/null 2>&1
}
open_master() {
  if $IS_DRY; then
    echo "${C_WARN}[DRY-RUN]${C_RST} ssh ${SSH_OPTS[*]} -N -f ${SSH_TARGET}  (ouverture connexion maître)"
    return 0
  fi
  log "Ouverture de la connexion SSH maître (multiplexage, 1 seule auth)…"
  if ! _try_master; then
    warn "Connexion maître refusée. Pause 8s puis 1 unique retry…"
    sleep 8
    _try_master || die "Connexion SSH maître impossible vers ${SSH_TARGET}.
    → Vérifie que ta clé publique est bien installée côté O2switch
      (cPanel → SSH Access → Manage SSH Keys) et que SSH_HOST/SSH_USER/SSH_PORT
      sont corrects. Ferme toute autre session SSH puis relance."
  fi
  ssh -o ControlPath="$CTRL_SOCK" -O check "$SSH_TARGET" >/dev/null 2>&1 \
    || die "Connexion maître ouverte mais contrôle KO (ControlPath=$CTRL_SOCK)."
  ok "Connexion maître active — rsync + commandes distantes la réutilisent."
}

# rsync : exclusions IMPÉRATIVES.
#   - .env / .env.*   : le .env LARAVEL local (APP_URL=weklo.localhost, DB locale)
#                       ne doit JAMAIS écraser le .env prod du serveur. Le serveur
#                       garde le sien, protégé par --delete sans --delete-excluded.
#   - deploy          : config de DÉPLOIEMENT (deploy/*.env : secrets SSH). Ne quitte
#                       jamais le local — exclue du rsync ET gitignorée. NB : distinct
#                       du .env Laravel ci-dessus, ne pas confondre les deux fichiers.
#   - .git/.github    : VCS / CI, inutiles en prod
#   - node_modules    : non requis (build déjà fait → public/build)
#   - tests / e2e     : pas en prod
#   - design-system   : source du DS (compilée dans public/build), inutile en prod
#   - docs / *.md     : documentation projet, hors prod
#   - /storage        : uploads utilisateurs (storage/app) + logs → JAMAIS touchés
#   - /public/storage : symlink recréé côté serveur par `artisan storage:link`
# Note : --delete NE supprime PAS les chemins exclus côté serveur (pas de
#        --delete-excluded), donc storage/ et un éventuel .env manuel sont protégés.
RSYNC_EXCLUDES=(
  --exclude='.env'
  --exclude='.env.*'
  --exclude='.git'
  --exclude='.github'
  --exclude='node_modules'
  --exclude='deploy'
  --exclude='tests'
  --exclude='e2e'
  --exclude='design-system'
  --exclude='docs'
  --exclude='.claude'
  --exclude='.idea'
  --exclude='.grepai'
  --exclude='cahier-des-charges.md'
  --exclude='compose.yaml'
  --exclude='docker-compose.e2e.yml'
  --exclude='Makefile'
  --exclude='/docker'
  --exclude='/storage'
  --exclude='/public/storage'
  --exclude='/.well-known'
  --exclude='/cgi-bin'
  --exclude='/core'
  --exclude='core'
)
run_rsync() {
  local -a flags=(-az --delete --human-readable "${RSYNC_EXCLUDES[@]}")
  $IS_DRY && flags+=(--dry-run)
  rsync "${flags[@]}" -e "$SSH_CMD" ./ "${SSH_TARGET}:${REMOTE_PATH}/"
}

# ===========================================================================
# 1. BUILD LOCAL — rien ne se compile côté serveur
# ===========================================================================
log "Étape 1/3 — Build local (composer + assets Vite)"
log "  composer : ${BUILD_COMPOSER}"
run_local $BUILD_COMPOSER install --no-dev --optimize-autoloader --no-interaction
ok "composer install --no-dev --optimize-autoloader"
# `--include=dev` : tout l'outillage de build (vite, tailwind, postcss) est en
# devDependencies. Si le shell appelant exporte NODE_ENV=production (cas des
# agents lancés depuis une app Electron), `npm ci` les omet et supprime ceux
# déjà installés → « vite: not found ». Les assets buildés ne les embarquent pas.
run_local $BUILD_NPM ci --include=dev
run_local $BUILD_NPM run build
ok "Assets buildés (public/build)"
echo

# ===========================================================================
# 2. RSYNC des artefacts vers le serveur
# ===========================================================================
log "Étape 2/3 — Synchronisation rsync vers ${SSH_TARGET}:${REMOTE_PATH}"
open_master
run_rsync
ok "Artefacts synchronisés (vendor/, public/build, code) — .env & storage préservés"
echo

# ===========================================================================
# 3. Côté serveur : squelette storage + migrations + caches Laravel
# ===========================================================================
log "Étape 3/3 — Finalisation côté serveur (SSH, php=${REMOTE_PHP})"

run_remote "mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache"
ok "Squelette storage/ + bootstrap/cache (idempotent)"

if [[ "$RUN_MIGRATIONS" == "true" ]]; then
  # ── GARDE-FOU EN DUR — NE JAMAIS WIPER LA BDD ─────────────────────────────
  # La SEULE commande DB autorisée est `migrate --force` (migrations additives).
  # Toute variante destructive (fresh/refresh/reset/wipe, drop, truncate, seed)
  # effacerait des données prod → refusée net, même injectée via une modif.
  MIGRATE_CMD="migrate --force"
  if echo "$MIGRATE_CMD" | grep -qiE 'fresh|refresh|reset|wipe|drop|truncate|db:seed'; then
    die "GARDE-FOU : commande de migration destructive interdite (« ${MIGRATE_CMD} »). Déploiement avorté pour protéger la BDD."
  fi
  run_remote "${REMOTE_PHP} artisan ${MIGRATE_CMD}"
  ok "${REMOTE_PHP} artisan ${MIGRATE_CMD}"
else
  warn "Migrations ignorées (RUN_MIGRATIONS=false)"
fi

run_remote "${REMOTE_PHP} artisan storage:link || true"
ok "storage:link (idempotent)"

run_remote "${REMOTE_PHP} artisan config:cache && ${REMOTE_PHP} artisan route:cache && ${REMOTE_PHP} artisan view:cache"
ok "config:cache / route:cache / view:cache"

echo
if $IS_DRY; then
  warn "DRY-RUN terminé — rsync simulé, aucune commande SSH distante exécutée."
else
  ok "${C_OK}Déploiement terminé avec succès.${C_RST}"
fi

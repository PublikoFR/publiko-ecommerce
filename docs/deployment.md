# Déploiement

## Cible : O2switch mutualisé (cPanel)

Deux environnements, même compte cPanel, deux dossiers/domaines :

| Env  | Domaine          | Racine app (`REMOTE_PATH`)          |
|------|------------------|-------------------------------------|
| PROD | `weklo.fr`       | `/home/<cpanel_user>/weklo.fr`      |
| DEV  | `dev.weklo.fr`   | `/home/<cpanel_user>/dev.weklo.fr`  |

Le *document root* de chaque (sous-)domaine doit pointer sur `REMOTE_PATH/public`.

## Modèle : rsync d'artefacts (build local → push)

Rien n'est compilé côté serveur. `deploy/deploy.sh` :

1. build **local** : `composer install --no-dev --optimize-autoloader` + `npm ci && npm run build` ;
2. **rsync** code + `vendor/` + `public/build` vers `REMOTE_PATH` ;
3. **SSH** : `artisan migrate --force` + `storage:link` + `config:cache`/`route:cache`/`view:cache`.

Détails d'usage, prérequis (clé SSH cPanel, `.env` serveur manuel, variables) :
**`deploy/README.md`**.

### Invariants de sécurité

- `.env` serveur et `/storage` (uploads + logs) sont **exclus du rsync** → jamais
  transférés ni écrasés (`--delete` sans `--delete-excluded`).
- Garde-fou DB en dur : seul `migrate --force` est autorisé ; toute commande
  destructive (`fresh`/`refresh`/`reset`/`wipe`/`drop`/`truncate`/`db:seed`) est
  refusée par le script.
- Configs de connexion dans `deploy/*.env` (**gitignorés**) ; seuls les
  `deploy/*.env.example` sont versionnés. `SSH_KEY` est un **chemin**, jamais la
  clé en clair.

## Domaine de dev local

Stack Docker Traefik : back-office sur `http://weklo.localhost/admin`,
phpMyAdmin sur `http://pma.weklo.localhost` (cf. `docs/architecture.md`).

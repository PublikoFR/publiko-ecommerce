# Déploiement Weklo → O2switch

Déploiement SSH/rsync vers le mutualisé **O2switch** (cPanel), pour **PROD** et
**DEV** (sous-domaine `dev.`).

## Modèle = rsync d'artefacts (build local, push du résultat)

On ne compile **rien côté serveur**. Tout est buildé en local puis poussé :

1. **Build local** : `composer install --no-dev --optimize-autoloader` +
   `npm ci && npm run build`.
2. **rsync** du code + `vendor/` + `public/build` vers `REMOTE_PATH`.
3. **SSH** : le serveur se contente de `migrate --force` + (re)construire les
   caches Laravel (`config:cache`, `route:cache`, `view:cache`) via `REMOTE_PHP`.

### Ce qui n'est JAMAIS touché côté serveur

`deploy.sh` exclut du rsync (et de sa suppression `--delete`) :

- **`.env`** — creds DB/APP posés à la main, jamais transférés ni écrasés ;
- **`/storage`** — `storage/app` (**uploads utilisateurs**) et `storage/logs` :
  jamais supprimés ni remplacés ;
- `.git`, `.github`, `node_modules`, `deploy`, `tests`, `e2e`, `design-system`,
  `docs`, `cahier-des-charges.md`, `compose.yaml`, `Makefile`, `/docker`,
  `public/storage` (symlink).

Le squelette `storage/framework/{cache,sessions,views}`, `storage/logs`,
`storage/app/public` et `bootstrap/cache` est **créé côté serveur s'il est
absent** (`mkdir -p` idempotent), sans jamais écraser l'existant.

### Garde-fou base de données

Le script n'exécute **que** `php artisan migrate --force`. Toute commande
destructive (`fresh`/`refresh`/`reset`/`wipe`/`drop`/`truncate`/`db:seed`) est
refusée en dur, même si elle est injectée dans le script.

## Prérequis (une seule fois)

1. **Clé SSH** : générer une paire dédiée et installer la **clé publique** côté
   O2switch (cPanel → **SSH Access** → **Manage SSH Keys** → Import/Authorize) :
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/o2switch_weklo_deploy -C "weklo-deploy"
   # puis autoriser ~/.ssh/o2switch_weklo_deploy.pub dans cPanel
   ssh -i ~/.ssh/o2switch_weklo_deploy -o IdentitiesOnly=yes <SSH_USER>@<SSH_HOST>
   ```

2. **Fichiers de config** : copier les `.example` et remplir les `TODO_*` :
   ```bash
   cp deploy/prod.env.example deploy/prod.env
   cp deploy/dev.env.example  deploy/dev.env
   ```
   Les `deploy/*.env` sont **gitignorés** ; seuls les `*.env.example` sont versionnés.

3. **Document root cPanel** : pour chaque domaine/sous-domaine, pointer le
   *document root* sur `REMOTE_PATH/public` (Laravel sert depuis `/public`).

4. **`.env` serveur** : poser **manuellement, une seule fois**, le `.env` Laravel
   dans `REMOTE_PATH` :
   - PROD : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://weklo.fr`,
     `APP_KEY=...`, creds DB O2switch.
   - DEV  : `APP_URL=https://dev.weklo.fr` + config de l'environnement dev.

   ⚠️ Ce `.env` serveur **n'est pas géré par le script** : il vit côté serveur,
   n'est ni transféré ni écrasé.

## Utilisation

```bash
# Dry-run : rsync --dry-run + commandes SSH affichées sans exécution
DRY_RUN=true bash deploy/deploy.sh deploy/prod.env

# Déploiement réel
bash deploy/deploy.sh deploy/prod.env    # PROD
bash deploy/deploy.sh deploy/dev.env     # DEV
```

## Variables (`deploy/<env>.env`)

| Variable         | Obligatoire | Défaut | Rôle |
|------------------|:-----------:|--------|------|
| `SSH_HOST`       | ✅          | —      | Hôte SSH O2switch (ex: nodeXXX.o2switch.net) |
| `SSH_USER`       | ✅          | —      | Identifiant cPanel |
| `SSH_PORT`       |             | `22`   | Port SSH |
| `SSH_KEY`        |             | —      | Chemin clé privée (`~` accepté) |
| `REMOTE_PATH`    | ✅          | —      | Racine de l'app Laravel (contient `artisan`) |
| `REMOTE_PHP`     |             | `php`  | Binaire php CLI ≥ 8.3 côté serveur |
| `RUN_MIGRATIONS` |             | `true` | Lancer `artisan migrate --force` |
| `DRY_RUN`        |             | `false`| `true` = simulation, aucune écriture distante |

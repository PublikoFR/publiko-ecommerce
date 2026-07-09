# Déploiement Weklo → O2switch

Déploiement SSH/rsync vers le mutualisé **O2switch** (cPanel), pour **PROD** et
**DEV** (sous-domaine `dev.`).

> **Deux fichiers `.env` distincts — ne pas confondre :**
> - **`deploy/*.env`** = config de **déploiement** (hôte/user/chemin/clé SSH).
>   Vit **uniquement en local** : gitignoré (jamais sur GitHub) + exclu du rsync
>   (jamais sur le serveur). Seuls les `deploy/*.env.example` (sans secret) sont versionnés.
> - **`.env` Laravel** = config de l'**application**. Le serveur a **le sien**
>   (creds prod). Le rsync l'exclut pour ne pas écraser le `.env` prod par le
>   `.env` local (qui pointe sur `weklo.localhost` + DB locale).

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
   ssh-keygen -t ed25519 -f ~/.ssh/weklo-o2switch -C "weklo-o2switch-deploy"
   # puis autoriser ~/.ssh/weklo-o2switch.pub dans cPanel
   ssh -i ~/.ssh/weklo-o2switch -o IdentitiesOnly=yes <SSH_USER>@<SSH_HOST>
   ```

2. **Fichiers de config** : copier les `.example` et remplir les `TODO_*` :
   ```bash
   cp deploy/prod.env.example deploy/prod.env
   cp deploy/dev.env.example  deploy/dev.env
   ```
   Les `deploy/*.env` sont **gitignorés** ; seuls les `*.env.example` sont versionnés.

3. **Document root cPanel** : pour chaque domaine/sous-domaine, pointer le
   *document root* sur `REMOTE_PATH/public` (Laravel sert depuis `/public`).

4. **`.env` Laravel serveur (prod & dev, distincts)** — voir section dédiée ci-dessous.

## `.env` Laravel serveur — prod & dev (bases distinctes)

Chaque environnement a **son propre `.env` Laravel** sur le serveur (base, URL,
APP_KEY différents). Templates prêts à remplir : `deploy/laravel/env.{prod,dev}.example`
(drivers adaptés au mutualisé : `file`/`sync`, pas de Redis).

Workflow (une fois par environnement) :

```bash
# 1. Bases MySQL : dans cPanel → « Bases de données MySQL », créer une base +
#    un utilisateur pour PROD et une autre paire pour DEV (préfixe goga8238_).

# 2. Générer une APP_KEY (distincte prod/dev) — en local, sans toucher ton .env :
docker compose -p ecom-laravel exec -u sail app php artisan key:generate --show
#    → copie la valeur base64:... dans APP_KEY du template correspondant.

# 3. Remplir les TODO du template (DB, APP_URL, mail, Stripe…) puis l'uploader
#    RENOMMÉ « .env » dans le REMOTE_PATH de l'env (cPanel Gestionnaire de fichiers
#    ou scp), une seule fois :
scp -i ~/.ssh/weklo-o2switch deploy/laravel/env.prod \
    goga8238@parc.o2switch.net:/home/goga8238/public_html/weklo/prod/.env
scp -i ~/.ssh/weklo-o2switch deploy/laravel/env.dev \
    goga8238@parc.o2switch.net:/home/goga8238/public_html/weklo/dev/.env
```

⚠️ Ces `.env` serveur **ne sont ni transférés ni écrasés** par `deploy.sh`
(exclus du rsync). Les copies remplies locales (`deploy/laravel/env.prod`,
`env.dev`) sont **gitignorées** ; seuls les `*.example` sont versionnés.

> **Premier déploiement d'un env** : `deploy.sh` fait `migrate --force` (schéma)
> mais **jamais** `db:seed` (garde-fou anti-wipe). Une base prod part donc vide
> de contenu applicatif : prévoir le bootstrap minimal (Shield + super-admin +
> réglages Storefront) hors pipeline — à cadrer avant la 1re mise en prod.

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

# Déploiement

## Cible : O2switch mutualisé (cPanel)

Deux environnements, même compte cPanel, deux dossiers/domaines :

| Env  | Domaine          | Racine app (`REMOTE_PATH`)                    |
|------|------------------|-----------------------------------------------|
| PROD | `weklo.fr`       | `/home/<cpanel_user>/public_html/weklo/prod`  |
| DEV  | `dev.weklo.fr`   | `/home/<cpanel_user>/public_html/weklo/dev`   |

(Les `deploy/*.env.example` versionnés portent encore l'ancien schéma
`/home/<user>/weklo.fr` — se fier aux `deploy/*.env` réels.)

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

## Checklist de première mise en production

Établie lors de la mise en service de DEV (29/07/2026). Le `.env` serveur étant
exclu du rsync, **rien de ce qui suit n'est déployé par le script** : ce sont des
opérations manuelles, à faire une fois, dans l'ordre.

### 1. Avant le premier déploiement

- **Cron Laravel** — une seule ligne par environnement, jamais une par tâche :
  ```cron
  * * * * * /usr/local/bin/php /home/<user>/public_html/weklo/prod/artisan schedule:run >> /dev/null 2>&1
  ```
  Tout le reste (worker de queue, imports IA, polling transporteurs et Pennylane)
  est déclaré dans `routes/console.php` et piloté par ce tick. Vérifier ensuite
  avec `php artisan schedule:list`.
- **Docroot** de `weklo.fr` = `public_html/weklo/prod/**public**`. Sinon « Index
  of / » et code source exposé.

### 2. `.env` de production

| Variable | Valeur | Pourquoi |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | `APP_DEBUG=true` colle une page Ignition à la fin de chaque réponse en cas d'erreur en `terminate()` — casse Alpine/Livewire (cf. `AppServiceProvider::boot()`) |
| `PENNYLANE_ENABLED` | `true` (défaut) | Rien à ajouter. **À forcer à `false` sur tout env non-prod** — cf. `docs/packages/pennylane.md` |
| `PENNYLANE_API_TOKEN` | token **de production** | Vérifier qu'aucun token de prod ne traîne sur dev |
| `LUNAR_STRIPE_WEBHOOK_SECRET` | `whsec_…` de l'endpoint prod | Sans lui le middleware rejette toute livraison en 400 |
| `STRIPE_PK` / `STRIPE_SECRET` | clés **live** | |
| `MAIL_*` | SMTP réel | `MAIL_SCHEME=smtps` pour le port 465 ; `MAIL_ENCRYPTION` est un reliquat Laravel 10, ignoré |
| `QUEUE_CONNECTION` | `database` | **En dernier** — voir §3 |

### 3. Bascule de la queue — l'ordre est impératif

1. Déployer le code (le worker est déjà dans `routes/console.php`).
2. Vérifier que le cron tourne : `php artisan schedule:list` doit lister
   `queue:work --stop-when-empty`.
3. **Puis seulement** passer `QUEUE_CONNECTION=database` dans le `.env`, suivi de
   `php artisan config:cache`.

L'ordre inverse empile les jobs dans la table `jobs` sans jamais les exécuter, et
**sans aucune erreur visible** : plus de facture, plus de mail, plus d'étiquette
transporteur. Détail des implications de `sync` : `docs/workflow.md`.

Validation de bout en bout (faite sur dev) : dispatcher un job, vérifier qu'il
apparaît dans `jobs`, qu'il disparaît en moins d'une minute sans intervention, et
que `failed_jobs` reste vide. Ne pas tester avec une closure passée à
`tinker --execute` : le worker ne sait pas la désérialiser (code `eval`) et
l'échec est un faux négatif — utiliser un vrai job de l'application.

### 4. Stripe

- Créer l'endpoint webhook `https://weklo.fr/stripe/webhook` en mode **live**,
  reporter le signing secret dans le `.env`.
- Le middleware ne traite que `payment_intent.succeeded` et
  `payment_intent.payment_failed` ; les autres events sont acquittés en 200.
- Une 500 dans la requête webhook laisse le statut de l'intent périmé en base et
  bloque le formulaire de carte au checkout suivant. `ResilientStripeManager`
  neutralise ce mode de panne (`docs/payments.md` §4.3), mais surveiller le taux
  d'échec des livraisons dans le dashboard Stripe reste utile.

### 5. Après déploiement

- `curl -s -o /dev/null -w "%{http_code}" https://weklo.fr/` et `/admin/login` → 200.
- Restaurer les dev-deps locales : le build a fait `composer install --no-dev` sur
  le `vendor/` partagé.
  ```bash
  docker compose -p ecom-laravel exec -T -u sail app composer install
  ```
- `php artisan queue:failed` → vide.

## Domaine de dev local

Stack Docker Traefik : back-office sur `http://weklo.localhost/admin`,
phpMyAdmin sur `http://pma.weklo.localhost` (cf. `docs/architecture.md`).

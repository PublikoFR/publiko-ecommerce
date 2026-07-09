---
description: Déployer Weklo en DEV sur O2switch (dev.weklo.fr), direct
---

# Déploiement DEV — Weklo (O2switch)

Tu pilotes le déploiement de l'environnement **DEV** (`dev.weklo.fr` →
`~/public_html/weklo/dev`). Modèle rsync : build local (composer dans le **conteneur
Docker** = PHP 8.3, npm sur l'hôte) puis push des artefacts. DEV se déploie
**directement, sans confirmation**.

## ⛔ RÈGLE DURE — base de données
La seule opération DB autorisée est `php artisan migrate --force` (additive, en
avant). **INTERDIT** : `migrate:fresh`/`refresh`/`reset`, `db:wipe`, `db:seed`
dans le pipeline, `DROP`/`TRUNCATE`, écrasement de dump. Ne modifie jamais
`deploy/deploy.sh` pour y glisser une commande destructive (le garde-fou du script
refuse déjà ces variantes).

## Deux `.env` à ne pas confondre
- `deploy/dev.env` = config de **déploiement** (SSH host/user/path/clé, `BUILD_COMPOSER`).
  Gitignoré, jamais sur le serveur.
- `.env` **Laravel** = config de l'app, vit **sur le serveur** dans `REMOTE_PATH`,
  posé à la main (creds DB O2switch), **exclu du rsync** → jamais transféré ni écrasé.

## Prérequis (vérifier, sinon STOPPER avec un message clair)
1. `deploy/dev.env` présent et rempli (sinon : « copie `deploy/dev.env.example` → `deploy/dev.env` »).
2. Clé SSH `~/.ssh/weklo-o2switch` présente et **autorisée** côté O2switch.
   Test rapide : `ssh -i ~/.ssh/weklo-o2switch -o IdentitiesOnly=yes goga8238@parc.o2switch.net "php -v"`
   (doit renvoyer PHP ≥ 8.3). Si timeout → IP à whitelister dans O2switch.
3. `.env` Laravel serveur présent dans `~/public_html/weklo/dev/` avec `DB_PASSWORD`
   rempli. Test connexion DB (sans afficher le mdp) :
   `ssh … 'cd ~/public_html/weklo/dev && PW=$(grep ^DB_PASSWORD= .env|cut -d= -f2-); mysql -u goga8238_admin -p"$PW" goga8238_weklo_dev -e "SELECT 1" '`
4. Docroot du sous-domaine `dev.weklo.fr` = `public_html/weklo/dev/**public**` (sinon
   « Index of / » + code exposé). Rappelle-le si `/admin/login` renvoie 404.

## Procédure
1. **Vérifier la config** (prérequis ci-dessus). Manquant → STOPPER.
2. **Déploiement direct** (DEV, pas de confirmation) :
   ```bash
   bash deploy/deploy.sh deploy/dev.env
   ```
   Le script : build composer (Docker PHP 8.3) + `npm run build` → rsync
   (exclut `.env`, `.env.*`, `storage`, `core`, `.well-known`, `cgi-bin`…) →
   `migrate --force` + `storage:link` + `config/route/view:cache`.
3. **Restaurer les dev-deps locales** (le build a fait `composer install --no-dev`
   sur le `vendor/` partagé) :
   ```bash
   docker compose -p ecom-laravel exec -T -u sail app composer install
   ```
4. **Premier déploiement d'une base neuve uniquement** (schéma migré mais vide) —
   bootstrap manuel côté serveur (démo OK en dev) :
   ```bash
   ssh … 'cd ~/public_html/weklo/dev && php artisan shield:generate --all --panel=admin --no-interaction && php artisan db:seed --force && php artisan shield:super-admin --user=1 --panel=admin'
   ```
5. **Vérifier** : `curl -s -o /dev/null -w "%{http_code}" https://dev.weklo.fr/` et
   `/admin/login` (attendu 200). Titre = « Weklo (dev) ».
6. **Afficher le résultat** final (succès/échec par étape) à l'utilisateur.

## Pièges connus (déjà rencontrés)
- Hôte en PHP 8.2 mais des deps exigent 8.3 → build composer **via Docker** (déjà
  câblé par `BUILD_COMPOSER` dans `deploy/dev.env`). Ne build jamais composer sur l'hôte.
- `view:cache` casse si un ServiceProvider fait `loadViewsFrom` sur un dossier
  inexistant → corriger le package (retirer le `loadViewsFrom` mort ou créer le dossier).
- `rsync --delete` sans exclusion supprimerait `.well-known` (AutoSSL) → déjà exclu.

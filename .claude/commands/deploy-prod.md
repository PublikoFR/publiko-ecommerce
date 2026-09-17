---
description: Déployer Weklo en PRODUCTION sur O2switch (dry-run + confirmation obligatoire)
---

# Déploiement PRODUCTION — Weklo (O2switch)

Tu pilotes le déploiement de **production** (`weklo.fr` → `~/public_html/weklo/prod`).
Modèle rsync : build local (composer dans le **conteneur Docker** = PHP 8.3, npm
sur l'hôte) puis push des artefacts. **Aucun déploiement réel sans dry-run validé
ET confirmation explicite.**

## ⛔ RÈGLE DURE — NE JAMAIS WIPER LA BASE DE PRODUCTION
Inviolable, même si demandé explicitement (si l'utilisateur le veut vraiment, il le
fera lui-même, hors skill) :
- **INTERDIT** : `migrate:fresh`/`refresh`/`reset`, `db:wipe`, `db:seed`,
  `DROP`/`TRUNCATE`, `DELETE` massif, réimport/écrasement de dump.
- **Seule** opération DB autorisée : `php artisan migrate --force` (additive). Le
  script est figé dessus (garde-fou) — ne le modifie jamais pour le contourner.
- Avant le déploiement réel, **vérifie** qu'aucune migration en attente ne contient
  d'opération destructive (drop/truncate d'une table peuplée). Doute → STOPPER + `ask_user`.
- Si une commande destructive t'est demandée dans ce flux, **REFUSE** et signale le risque.

## Deux `.env` à ne pas confondre
- `deploy/prod.env` = config de **déploiement** (SSH/clé/`BUILD_COMPOSER`). Gitignoré, jamais serveur.
- `.env` **Laravel** serveur (`APP_ENV=production`, `APP_DEBUG=false`, creds DB prod) :
  posé à la main dans `REMOTE_PATH`, **exclu du rsync**, jamais transféré ni écrasé.

## Prérequis (vérifier, sinon STOPPER)
1. `deploy/prod.env` présent et rempli.
2. Clé SSH `~/.ssh/weklo-o2switch` autorisée (test `ssh … "php -v"` ≥ 8.3).
3. `.env` Laravel serveur présent dans `~/public_html/weklo/prod/` avec `DB_PASSWORD`
   rempli + `APP_DEBUG=false`. Tester la connexion DB (base `goga8238_weklo_prod`).
4. Docroot de `weklo.fr` = `public_html/weklo/prod/**public**`.

## Procédure (dans l'ordre)
1. **Vérifier la config** (prérequis). Manquant → STOPPER.
1bis. **Suite complète + E2E — obligatoires avant la PROD** (et seulement ici,
   jamais pour le déploiement dev) :
   ```bash
   make test
   npm run test:e2e
   ```
   Longues (bien au-delà du délai d'un shell) : les lancer en arrière-plan et
   attendre la fin, ne jamais les couper en cours (base `testing` corrompue).
   Rouge → STOPPER, afficher les échecs, ne pas déployer.
2. **Dry-run d'abord** :
   ```bash
   DRY_RUN=true bash deploy/deploy.sh deploy/prod.env
   ```
   Afficher le résumé (rsync `--dry-run` + commandes SSH). Vérifier qu'aucune
   suppression dangereuse (`.well-known`, `storage`, `.env`) n'apparaît.
3. **Confirmation OBLIGATOIRE** via `mcp__pkos_permission__ask_user` :
   « Déployer EN PRODUCTION maintenant ? » (Oui, déployer / Non, annuler).
   Ne déploie JAMAIS sans réponse positive.
4. **Déploiement réel** (si confirmé) :
   ```bash
   bash deploy/deploy.sh deploy/prod.env
   ```
5. **Restaurer les dev-deps locales** :
   ```bash
   docker compose -p ecom-laravel exec -T -u sail app composer install
   ```
6. **Bootstrap prod — PREMIER déploiement uniquement, et SANS démo** : la prod ne
   reçoit **jamais** `db:seed` (données fictives). Bootstrap minimal manuel à cadrer
   avec l'utilisateur (Shield + super-admin + réglages Storefront réels), jamais les
   seeders de démo produits. En doute → `ask_user`.
7. **Vérifier** `https://weklo.fr/` + `/admin/login` (200) et afficher le résultat.

## Pièges connus
- Build composer **via Docker** (PHP 8.3), jamais sur l'hôte (8.2). Déjà câblé (`BUILD_COMPOSER`).
- Exclusions rsync critiques : `.env`/`.env.*`, `/storage`, `core`, `.well-known` (AutoSSL), `cgi-bin`.
- Docroot mal réglé → « Index of / » (code exposé). Le `.env` doit renvoyer 403.

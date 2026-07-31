# bash explicite : `set -o pipefail` (cible db-dump) est indisponible sous dash,
# le /bin/sh par defaut de make sur Debian/Ubuntu. Sans pipefail, un mysqldump en
# echec produit une archive gzip vide dont le code retour est 0 → faux backup.
SHELL := /bin/bash

# Garde anti-wipe : detecte si make tourne depuis un worktree PKOS.
# container_name est fige dans compose.yaml → un worktree retombe sur le
# conteneur principal = base de dev weklo. On bloque les commandes destructives
# et on propage PKOS_WORKTREE=1 dans le conteneur pour la garde framework
# (DB::prohibitDestructiveCommands dans AppServiceProvider).
WORKTREE_GUARD := $(findstring /.pkos/worktrees/,$(CURDIR))
WT_ENV := $(if $(WORKTREE_GUARD),-e PKOS_WORKTREE=1,)

# Isolation DB par worktree : derive un nom de base unique depuis le task ID
# (12 premiers chars du nom de dossier, sans tirets) → testing_<slug>.
# Cree la base via root (seul l'user root peut CREATE DATABASE) + grante weklo.
# Sans worktree → base standard "testing".
# L'user applicatif weklo ne peut pas CREATE DATABASE (SHOW GRANTS confirme) →
# GRANT obligatoire via root. Mot de passe lu depuis .env sinon defaut compose.yaml.
WT_TASK_SLUG := $(if $(WORKTREE_GUARD),$(shell basename $(CURDIR) | tr -d '-' | cut -c1-12),)
WT_DB_NAME   := $(if $(WORKTREE_GUARD),testing_$(WT_TASK_SLUG),testing)
DB_ROOT_PWD  := $(or $(shell grep -s '^DB_ROOT_PASSWORD=' .env 2>/dev/null | cut -d= -f2),root_password)

# Chemin du repo principal (pour partager vendor/ + .env dans docker run worktree).
# vendor/ n'est pas tracke en git → non present dans le worktree.
# Les symlinks vendor/pko/* → ../../packages/pko/* resolvent vers les packages
# du worktree quand vendor est monte depuis le repo principal.
MAIN_REPO := $(if $(WORKTREE_GUARD),$(shell git worktree list --porcelain 2>/dev/null | grep '^worktree' | head -1 | awk '{print $$2}'),)

# Dump de securite avant toute action destructive sur la base de dev.
# Les trois wipes de weklo (2026-06-02, 2026-07-16, 2026-07-29) ont ete des pertes
# seches faute de sauvegarde. Toute cible qui touche au schema ou aux donnees de la
# base de dev depend desormais de `db-dump`.
BACKUP_DIR  := storage/backups
BACKUP_KEEP := 20
DB_NAME     := $(or $(shell grep -s '^DB_DATABASE=' .env 2>/dev/null | cut -d= -f2),weklo)

DC=docker compose -p ecom-laravel
EXEC=$(DC) exec -u sail $(WT_ENV) app
EXEC_ROOT=$(DC) exec $(WT_ENV) app

.PHONY: help install build up down restart shell artisan composer migrate fresh seed test test-only lint logs ps lunar shield permissions db-dump db-restore

help:
	@echo "Back-office Laravel + Lunar + Filament"
	@echo ""
	@echo "Usage :"
	@echo "  make install     Première installation (build + up + composer + migrate + lunar + shield + seed)"
	@echo "  make build       Reconstruire l'image app"
	@echo "  make up          Démarrer les conteneurs"
	@echo "  make down        Arrêter les conteneurs"
	@echo "  make restart     Redémarrer les conteneurs"
	@echo "  make shell       Shell bash dans le conteneur app"
	@echo "  make artisan     Lancer artisan : make artisan CMD='migrate:status'"
	@echo "  make composer    Lancer composer : make composer CMD='dump-autoload'"
	@echo "  make migrate     Exécuter les migrations"
	@echo "  make fresh       migrate:fresh --seed (reset DB complet)"
	@echo "  make seed        Exécuter les seeders"
	@echo "  make db-dump     Sauvegarder la base de dev (auto avant toute action destructive)"
	@echo "  make db-restore  Restaurer le dump le plus récent (ou DUMP=<chemin>)"
	@echo "  make test        Lancer la suite PHPUnit"
	@echo "  make lint        Laravel Pint (PSR-12)"
	@echo "  make logs        Suivre les logs des conteneurs"
	@echo "  make ps          Statut des conteneurs"
	@echo "  make lunar       Relancer lunar:install"
	@echo "  make shield      Générer les policies Shield (--all)"
	@echo "  make shield-sync Shield generate + super-admin regrant + caches clear (apres ajout Resource/Page/Cluster)"
	@echo "  make permissions Corriger les permissions storage + bootstrap/cache"
	@echo ""
	@echo "URLs :"
	@echo "  Back-office    http://weklo.localhost/admin"
	@echo "  phpMyAdmin     http://pma.weklo.localhost"
	@echo "  Mailpit        http://mailpit.localhost (shared)"

install: db-dump
	@if [ -n "$(WORKTREE_GUARD)" ]; then echo "⛔ Commande destructive interdite depuis un worktree PKOS (protège la base de dev weklo). Utilise 'make test' (DB testing) pour valider une migration."; exit 1; fi
	$(DC) up -d --build
	$(EXEC) composer install
	$(MAKE) permissions
	$(EXEC) php artisan key:generate --force
	$(EXEC) php artisan storage:link
	$(EXEC) php artisan migrate --graceful --force
# Cf. la cible `fresh` : lunar:install prompte si aucun Staff admin n'existe.
	$(EXEC) php artisan db:seed --class='Database\Seeders\PkoAdminUserSeeder' --force
	$(EXEC) php artisan lunar:install --no-interaction
	$(EXEC) php artisan shield:generate --all --panel=admin --no-interaction
	$(EXEC) php artisan db:seed --force
	$(EXEC) php artisan shield:super-admin --user=1 --panel=admin

build:
	$(DC) build --no-cache

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) restart

shell:
	$(EXEC) bash

artisan:
	$(EXEC) php artisan $(CMD)

composer:
	$(EXEC) composer $(CMD)

migrate: db-dump
	$(EXEC) php artisan migrate

# Dump gzip horodate de la base de dev vers storage/backups/, avec retention
# glissante. Echoue bruyamment (pipefail) plutot que d'ecrire une archive vide :
# un backup silencieusement casse est pire que pas de backup.
db-dump:
	@if [ -n "$(WORKTREE_GUARD)" ]; then echo "⛔ Dump interdit depuis un worktree PKOS."; exit 1; fi
	@mkdir -p $(BACKUP_DIR)
	@TABLES=$$($(DC) exec -T mysql sh -c 'exec mysql -N -B -uroot -p"$$MYSQL_ROOT_PASSWORD" -e "select count(*) from information_schema.tables where table_schema=\"$(DB_NAME)\""' 2>/dev/null | tr -d '\r'); \
	if [ -z "$$TABLES" ] || [ "$$TABLES" = "0" ]; then echo "ℹ️  Base '$(DB_NAME)' absente ou vide — rien à sauvegarder."; exit 0; fi; \
	set -o pipefail; \
	OUT="$(BACKUP_DIR)/$(DB_NAME)-$$(date +%Y%m%d-%H%M%S).sql.gz"; \
	$(DC) exec -T mysql sh -c 'exec mysqldump --single-transaction --routines --events --no-tablespaces -uroot -p"$$MYSQL_ROOT_PASSWORD" $(DB_NAME)' 2>/dev/null | gzip > "$$OUT" || { echo "⛔ Dump de '$(DB_NAME)' échoué — action destructive annulée."; rm -f "$$OUT"; exit 1; }; \
	if [ ! -s "$$OUT" ]; then echo "⛔ Dump vide — action destructive annulée."; rm -f "$$OUT"; exit 1; fi; \
	echo "💾 Sauvegarde : $$OUT ($$(du -h "$$OUT" | cut -f1))"
	@ls -1t $(BACKUP_DIR)/$(DB_NAME)-*.sql.gz 2>/dev/null | tail -n +$$(($(BACKUP_KEEP) + 1)) | xargs -r rm -f

# Restaure le dump le plus recent (ou DUMP=<chemin>).
db-restore:
	@DUMP="$(or $(DUMP),$(shell ls -1t $(BACKUP_DIR)/*.sql.gz 2>/dev/null | head -1))"; \
	if [ -z "$$DUMP" ] || [ ! -s "$$DUMP" ]; then echo "⛔ Aucun dump exploitable dans $(BACKUP_DIR)/. Précise DUMP=<chemin>."; exit 1; fi; \
	echo "♻️  Restauration de $$DUMP dans '$(DB_NAME)'..."; \
	gunzip -c "$$DUMP" | $(DC) exec -T mysql sh -c 'exec mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" $(DB_NAME)' 2>/dev/null && echo "✅ Base '$(DB_NAME)' restaurée."

fresh: db-dump
	@if [ -n "$(WORKTREE_GUARD)" ]; then echo "⛔ Commande destructive interdite depuis un worktree PKOS (protège la base de dev weklo). Utilise 'make test' (DB testing) pour valider une migration."; exit 1; fi
	$(EXEC) php artisan storage:link
	$(EXEC) sh -c 'ALLOW_DB_WIPE=1 php artisan migrate:fresh --force'
# lunar:install appelle lunar:create-admin des qu'aucun Staff admin n'existe, et ce
# sous-appel prompte MEME avec --no-interaction → 'Interactivity.php line 32: Required.'
# et la cible s'arrete juste apres le wipe, base vide. Semer l'admin d'abord rend la
# condition fausse et l'installation non interactive.
	$(EXEC) php artisan db:seed --class='Database\Seeders\PkoAdminUserSeeder' --force
	$(EXEC) php artisan lunar:install --no-interaction
	$(EXEC) php artisan shield:generate --all --panel=admin --no-interaction
	$(EXEC) php artisan db:seed --force
	$(EXEC) php artisan shield:super-admin --user=1 --panel=admin

seed: db-dump
	$(EXEC) php artisan db:seed

test:
	@if [ -n "$(WORKTREE_GUARD)" ]; then \
		echo "→ [worktree] DB isolee : $(WT_DB_NAME) | code : $(CURDIR)"; \
		docker exec weklo-mysql mysql -u root -p$(DB_ROOT_PWD) -e \
			"CREATE DATABASE IF NOT EXISTS \`$(WT_DB_NAME)\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`$(WT_DB_NAME)\`.* TO 'weklo'@'%'; FLUSH PRIVILEGES;" ; \
		docker exec weklo-app sh -c "pkill -9 -f 'artisan test|phpunit' 2>/dev/null; exit 0" ; \
		docker run --rm -u sail -w /var/www/html \
			--network ecom-laravel_backend \
			-v "$(CURDIR):/var/www/html" \
			-v "$(MAIN_REPO)/vendor:/var/www/html/vendor" \
			-v "$(MAIN_REPO)/.env:/var/www/html/.env:ro" \
			-v "$(MAIN_REPO)/docker/app/php.ini:/usr/local/etc/php/conf.d/zz-weklo.ini:ro" \
			-v "$(MAIN_REPO)/public/build:/var/www/html/public/build:ro" \
			-v "$(MAIN_REPO)/storage:/var/www/html/storage" \
			-v "$(MAIN_REPO)/bootstrap/cache:/var/www/html/bootstrap/cache" \
			-e PKOS_WORKTREE=1 \
			-e DB_DATABASE=$(WT_DB_NAME) \
			ecom-laravel-app sh scripts/run-tests-chunked.sh ; \
	else \
		docker exec weklo-app sh -c "pkill -9 -f 'artisan test|phpunit' 2>/dev/null; exit 0" ; \
		$(EXEC) sh scripts/run-tests-chunked.sh ; \
	fi

# Suite CIBLEE — meme base de test que `make test`, sur un sous-ensemble.
#
# POURQUOI : `make test` lance les 13 chunks (~7 min 30). Dans une boucle de
# correction on refait tourner la suite entiere pour valider une ligne, ce qui
# coute des dizaines de minutes par mission. Cette cible ramene le cycle a
# ~30 s.
#
# AUSSI SUR QUE `make test` : la securite ne vient pas du fait de tout lancer,
# elle vient de phpunit.xml qui force `DB_DATABASE=testing` (ligne 25). Un
# `php artisan test <chemin>` herite exactement du meme env, donc de la meme
# base cible. Il ne peut pas toucher la base de dev.
#
# SEGFAULT : le decoupage en chunks existe parce que >250 tests dans un seul
# process PHP segfaultent (cf. scripts/run-tests-chunked.sh). Un run cible est
# par construction un seul petit chunk, tres en-dessous du seuil. On garde
# quand meme le `ulimit -s` du script par symetrie.
#
# USAGE :
#   make test-only T=tests/Feature/Checkout
#   make test-only T=tests/Feature/Checkout/CheckoutBindingTest.php
#   make test-only T='--filter=it_binds_the_cart'
#
# La suite COMPLETE (`make test`) reste obligatoire avant tout merge.
test-only:
	@if [ -z "$(T)" ]; then \
		echo "make test-only : precise la cible avec T=..." ; \
		echo "  ex. make test-only T=tests/Feature/Checkout" ; \
		exit 2 ; \
	fi
	@if [ -n "$(WORKTREE_GUARD)" ]; then \
		echo "→ [worktree] DB isolee : $(WT_DB_NAME) | code : $(CURDIR)"; \
		docker exec weklo-mysql mysql -u root -p$(DB_ROOT_PWD) -e \
			"CREATE DATABASE IF NOT EXISTS \`$(WT_DB_NAME)\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`$(WT_DB_NAME)\`.* TO 'weklo'@'%'; FLUSH PRIVILEGES;" ; \
		docker exec weklo-app sh -c "pkill -9 -f 'artisan test|phpunit' 2>/dev/null; exit 0" ; \
		docker run --rm -u sail -w /var/www/html \
			--network ecom-laravel_backend \
			-v "$(CURDIR):/var/www/html" \
			-v "$(MAIN_REPO)/vendor:/var/www/html/vendor" \
			-v "$(MAIN_REPO)/.env:/var/www/html/.env:ro" \
			-v "$(MAIN_REPO)/docker/app/php.ini:/usr/local/etc/php/conf.d/zz-weklo.ini:ro" \
			-v "$(MAIN_REPO)/public/build:/var/www/html/public/build:ro" \
			-v "$(MAIN_REPO)/storage:/var/www/html/storage" \
			-v "$(MAIN_REPO)/bootstrap/cache:/var/www/html/bootstrap/cache" \
			-e PKOS_WORKTREE=1 \
			-e DB_DATABASE=$(WT_DB_NAME) \
			ecom-laravel-app sh -c "ulimit -s 65536 2>/dev/null || true; php artisan test $(T)" ; \
	else \
		docker exec weklo-app sh -c "pkill -9 -f 'artisan test|phpunit' 2>/dev/null; exit 0" ; \
		$(EXEC) sh -c "ulimit -s 65536 2>/dev/null || true; php artisan test $(T)" ; \
	fi

lint:
	$(EXEC) ./vendor/bin/pint

logs:
	$(DC) logs -f

# Suivre le worker de file (creation d'etiquettes, conversions media, e-mails).
queue-logs:
	$(DC) logs -f queue

# Etat de la file : nombre de jobs en attente, par type.
queue-status:
	$(EXEC) php artisan queue:monitor default

ps:
	$(DC) ps

lunar: db-dump
	@if [ -n "$(WORKTREE_GUARD)" ]; then echo "⛔ Commande destructive interdite depuis un worktree PKOS (protège la base de dev weklo). Utilise 'make test' (DB testing) pour valider une migration."; exit 1; fi
	$(EXEC) php artisan lunar:install

shield:
	$(EXEC) php artisan shield:generate --all --panel=admin

# Sync complet apres ajout d'une Resource / Page / Cluster Filament :
# regenere les policies Shield + re-attribue toutes les permissions au super-admin
# + purge les caches (route/view/config) pour que Filament re-decouvre la nav.
# A lancer systematiquement apres creation d'un nouvel element de navigation.
shield-sync:
	$(EXEC) php artisan shield:generate --all --panel=admin --no-interaction
	$(EXEC) php artisan shield:super-admin --user=1 --panel=admin
	$(EXEC) php artisan optimize:clear

permissions:
	$(EXEC_ROOT) chown -R sail:sail storage bootstrap/cache
	$(EXEC_ROOT) chmod -R ug+rwX storage bootstrap/cache

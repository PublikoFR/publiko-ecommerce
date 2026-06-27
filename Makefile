# Garde anti-wipe : detecte si make tourne depuis un worktree PKOS.
# container_name est fige dans compose.yaml → un worktree retombe sur le
# conteneur principal = base de dev mde. On bloque les commandes destructives
# et on propage PKOS_WORKTREE=1 dans le conteneur pour la garde framework
# (DB::prohibitDestructiveCommands dans AppServiceProvider).
WORKTREE_GUARD := $(findstring /.pkos/worktrees/,$(CURDIR))
WT_ENV := $(if $(WORKTREE_GUARD),-e PKOS_WORKTREE=1,)

# Isolation DB par worktree : derive un nom de base unique depuis le task ID
# (12 premiers chars du nom de dossier, sans tirets) → testing_<slug>.
# Cree la base via root (seul l'user root peut CREATE DATABASE) + grante mde.
# Sans worktree → base standard "testing".
# L'user applicatif mde ne peut pas CREATE DATABASE (SHOW GRANTS confirme) →
# GRANT obligatoire via root. Mot de passe lu depuis .env sinon defaut compose.yaml.
WT_TASK_SLUG := $(if $(WORKTREE_GUARD),$(shell basename $(CURDIR) | tr -d '-' | cut -c1-12),)
WT_DB_NAME   := $(if $(WORKTREE_GUARD),testing_$(WT_TASK_SLUG),testing)
DB_ROOT_PWD  := $(or $(shell grep -s '^DB_ROOT_PASSWORD=' .env 2>/dev/null | cut -d= -f2),root_password)

# Chemin du repo principal (pour partager vendor/ + .env dans docker run worktree).
# vendor/ n'est pas tracke en git → non present dans le worktree.
# Les symlinks vendor/pko/* → ../../packages/pko/* resolvent vers les packages
# du worktree quand vendor est monte depuis le repo principal.
MAIN_REPO := $(if $(WORKTREE_GUARD),$(shell git worktree list --porcelain 2>/dev/null | grep '^worktree' | head -1 | awk '{print $$2}'),)

DC=docker compose -p ecom-laravel
EXEC=$(DC) exec -u sail $(WT_ENV) app
EXEC_ROOT=$(DC) exec $(WT_ENV) app

.PHONY: help install build up down restart shell artisan composer migrate fresh seed test lint logs ps lunar shield permissions

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
	@echo "  Back-office    http://mde-laravel.localhost/admin"
	@echo "  phpMyAdmin     http://pma.mde-laravel.localhost"
	@echo "  Mailpit        http://mailpit.localhost (shared)"

install:
	@if [ -n "$(WORKTREE_GUARD)" ]; then echo "⛔ Commande destructive interdite depuis un worktree PKOS (protège la base de dev mde). Utilise 'make test' (DB testing) pour valider une migration."; exit 1; fi
	$(DC) up -d --build
	$(EXEC) composer install
	$(MAKE) permissions
	$(EXEC) php artisan key:generate --force
	$(EXEC) php artisan storage:link
	$(EXEC) php artisan migrate --graceful --force
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

migrate:
	$(EXEC) php artisan migrate

fresh:
	@if [ -n "$(WORKTREE_GUARD)" ]; then echo "⛔ Commande destructive interdite depuis un worktree PKOS (protège la base de dev mde). Utilise 'make test' (DB testing) pour valider une migration."; exit 1; fi
	$(EXEC) php artisan storage:link
	$(EXEC) php artisan migrate:fresh --force
	$(EXEC) php artisan lunar:install --no-interaction
	$(EXEC) php artisan shield:generate --all --panel=admin --no-interaction
	$(EXEC) php artisan db:seed --force
	$(EXEC) php artisan shield:super-admin --user=1 --panel=admin

seed:
	$(EXEC) php artisan db:seed

test:
	@if [ -n "$(WORKTREE_GUARD)" ]; then \
		echo "→ [worktree] DB isolee : $(WT_DB_NAME) | code : $(CURDIR)"; \
		docker exec mde-laravel-mysql mysql -u root -p$(DB_ROOT_PWD) -e \
			"CREATE DATABASE IF NOT EXISTS \`$(WT_DB_NAME)\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`$(WT_DB_NAME)\`.* TO 'mde'@'%'; FLUSH PRIVILEGES;" ; \
		docker exec mde-laravel-app sh -c "pkill -9 -f 'artisan test|phpunit' 2>/dev/null; exit 0" ; \
		docker run --rm -u sail -w /var/www/html \
			--network ecom-laravel_backend \
			-v "$(CURDIR):/var/www/html" \
			-v "$(MAIN_REPO)/vendor:/var/www/html/vendor" \
			-v "$(MAIN_REPO)/.env:/var/www/html/.env:ro" \
			-v "$(MAIN_REPO)/docker/app/php.ini:/usr/local/etc/php/conf.d/zz-mde.ini:ro" \
			-v "$(MAIN_REPO)/public/build:/var/www/html/public/build:ro" \
			-v "$(MAIN_REPO)/storage:/var/www/html/storage" \
			-v "$(MAIN_REPO)/bootstrap/cache:/var/www/html/bootstrap/cache" \
			-e PKOS_WORKTREE=1 \
			-e DB_DATABASE=$(WT_DB_NAME) \
			ecom-laravel-app php artisan test ; \
	else \
		docker exec mde-laravel-app sh -c "pkill -9 -f 'artisan test|phpunit' 2>/dev/null; exit 0" ; \
		$(EXEC) php artisan test ; \
	fi

lint:
	$(EXEC) ./vendor/bin/pint

logs:
	$(DC) logs -f

ps:
	$(DC) ps

lunar:
	@if [ -n "$(WORKTREE_GUARD)" ]; then echo "⛔ Commande destructive interdite depuis un worktree PKOS (protège la base de dev mde). Utilise 'make test' (DB testing) pour valider une migration."; exit 1; fi
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

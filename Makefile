DC=docker compose
EXEC=$(DC) exec -u sail app
EXEC_ROOT=$(DC) exec app

.PHONY: help install build up down restart shell artisan composer migrate fresh seed test lint logs ps lunar shield permissions

help:
	@echo "MDE Distribution — Back-office Laravel + Lunar + Filament"
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
	@echo "  make seed        Exécuter les seeders MDE"
	@echo "  make test        Lancer la suite PHPUnit"
	@echo "  make lint        Laravel Pint (PSR-12)"
	@echo "  make logs        Suivre les logs des conteneurs"
	@echo "  make ps          Statut des conteneurs"
	@echo "  make lunar       Relancer lunar:install"
	@echo "  make shield      Générer les policies Shield (--all)"
	@echo "  make permissions Corriger les permissions storage + bootstrap/cache"
	@echo ""
	@echo "URLs :"
	@echo "  Back-office    http://mde-laravel.localhost/admin"
	@echo "  phpMyAdmin     http://pma.mde-laravel.localhost"
	@echo "  Mailpit        http://mailpit.localhost (shared)"

install:
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
	$(EXEC) php artisan storage:link
	$(EXEC) php artisan migrate:fresh --force
	$(EXEC) php artisan lunar:install --no-interaction
	$(EXEC) php artisan shield:generate --all --panel=admin --no-interaction
	$(EXEC) php artisan db:seed --force
	$(EXEC) php artisan shield:super-admin --user=1 --panel=admin

seed:
	$(EXEC) php artisan db:seed

test:
	$(EXEC) php artisan test

lint:
	$(EXEC) ./vendor/bin/pint

logs:
	$(DC) logs -f

ps:
	$(DC) ps

lunar:
	$(EXEC) php artisan lunar:install

shield:
	$(EXEC) php artisan shield:generate --all --panel=admin

permissions:
	$(EXEC_ROOT) chown -R sail:sail storage bootstrap/cache
	$(EXEC_ROOT) chmod -R ug+rwX storage bootstrap/cache

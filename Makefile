SAIL=./vendor/bin/sail

.PHONY: help install up down restart shell artisan composer migrate fresh seed test lint logs lunar shield

help:
	@echo "MDE Distribution — Back-office Laravel + Lunar + Filament"
	@echo ""
	@echo "Usage :"
	@echo "  make install   Première installation complète (build + up + migrate + lunar + shield + seed)"
	@echo "  make up        Démarrer l'environnement Docker (Sail)"
	@echo "  make down      Arrêter l'environnement"
	@echo "  make restart   Redémarrer les conteneurs"
	@echo "  make shell     Shell dans le conteneur applicatif"
	@echo "  make artisan   Lancer une commande artisan : make artisan CMD='migrate:status'"
	@echo "  make composer  Lancer composer : make composer CMD='dump-autoload'"
	@echo "  make migrate   Exécuter les migrations"
	@echo "  make fresh     migrate:fresh --seed (reset DB complet)"
	@echo "  make seed      Exécuter les seeders MDE"
	@echo "  make test      Lancer la suite PHPUnit"
	@echo "  make lint      Laravel Pint (PSR-12)"
	@echo "  make logs      Suivre les logs Sail"
	@echo "  make lunar     Relancer lunar:install"
	@echo "  make shield    Générer les policies Shield (--all)"

install:
	$(SAIL) up -d --build
	$(SAIL) artisan migrate --graceful --force
	$(SAIL) artisan lunar:install
	$(SAIL) artisan shield:install admin --no-interaction
	$(SAIL) artisan shield:generate --all --panel=admin --no-interaction
	$(SAIL) artisan db:seed --force

up:
	$(SAIL) up -d

down:
	$(SAIL) down

restart:
	$(SAIL) restart

shell:
	$(SAIL) shell

artisan:
	$(SAIL) artisan $(CMD)

composer:
	$(SAIL) composer $(CMD)

migrate:
	$(SAIL) artisan migrate

fresh:
	$(SAIL) artisan migrate:fresh --seed

seed:
	$(SAIL) artisan db:seed

test:
	$(SAIL) artisan test

lint:
	$(SAIL) composer exec pint

logs:
	$(SAIL) logs -f

lunar:
	$(SAIL) artisan lunar:install

shield:
	$(SAIL) artisan shield:generate --all --panel=admin

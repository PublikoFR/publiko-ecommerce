<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Décide si les commandes destructives (migrate:fresh / migrate:refresh /
 * migrate:reset / db:wipe) doivent être bloquées au niveau framework.
 *
 * Le critère est le NOM DE LA BASE RÉELLEMENT CIBLÉE, jamais APP_ENV : un simple
 * `--env=testing` sur la ligne de commande bascule l'environnement applicatif sans
 * changer la connexion quand `.env.testing` n'existe pas — la base de dev est alors
 * ciblée avec la garde désactivée. Incident du 29/07/2026 sur `weklo`.
 */
final class DestructiveCommandGuard
{
    /**
     * @param  string  $database  Base ciblée par la connexion par défaut.
     * @param  string  $environment  Valeur d'APP_ENV (sert uniquement au verrou prod).
     * @param  bool  $allowWipe  Bypass explicite `ALLOW_DB_WIPE=1`, porté par `make fresh`.
     */
    public static function shouldProhibit(string $database, string $environment, bool $allowWipe): bool
    {
        // Production : verrou absolu, aucun bypass possible.
        if ($environment === 'production') {
            return true;
        }

        // Bases de test (`testing`, `testing_<hash>` du run parallèle) : les suites
        // doivent pouvoir se rafraîchir librement.
        if (self::isTestDatabase($database)) {
            return false;
        }

        // Toute autre base (dev / local) : bloqué sauf bypass humain explicite.
        return ! $allowWipe;
    }

    public static function isTestDatabase(string $database): bool
    {
        // basename() couvre les connexions SQLite qui portent un chemin de fichier.
        $name = basename($database);

        // `pko_e2e` : base de la stack Playwright jetable (docker-compose.e2e.yml),
        // créée et détruite à chaque run — son global-setup DOIT pouvoir jouer
        // `migrate:fresh --seed`. Préfixe `pko_` exigé pour ne pas dégrader la garde
        // sur une éventuelle base métier dont le nom contiendrait « e2e ».
        return str_starts_with($name, 'testing') || $name === 'pko_e2e';
    }
}

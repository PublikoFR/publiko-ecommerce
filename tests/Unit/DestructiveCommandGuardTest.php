<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DestructiveCommandGuard;
use PHPUnit\Framework\TestCase;

class DestructiveCommandGuardTest extends TestCase
{
    /**
     * Régression de l'incident du 29/07/2026 : `php artisan migrate:fresh --env=testing`
     * bascule APP_ENV à `testing` sans changer la connexion (pas de `.env.testing`),
     * la base de dev est donc ciblée. L'ancienne garde, basée sur APP_ENV, laissait
     * passer et la base `weklo` a été vidée.
     */
    public function test_env_testing_ne_debloque_pas_le_wipe_de_la_base_de_dev(): void
    {
        $this->assertTrue(DestructiveCommandGuard::shouldProhibit(
            database: 'weklo',
            environment: 'testing',
            allowWipe: false,
        ));
    }

    public function test_base_de_dev_bloquee_en_local(): void
    {
        $this->assertTrue(DestructiveCommandGuard::shouldProhibit('weklo', 'local', false));
    }

    public function test_base_de_dev_autorisee_avec_le_bypass_explicite(): void
    {
        $this->assertFalse(DestructiveCommandGuard::shouldProhibit('weklo', 'local', true));
    }

    public function test_bases_de_test_toujours_autorisees(): void
    {
        $this->assertFalse(DestructiveCommandGuard::shouldProhibit('testing', 'testing', false));
        $this->assertFalse(DestructiveCommandGuard::shouldProhibit('testing_a1b2c3', 'local', false));
        $this->assertFalse(DestructiveCommandGuard::shouldProhibit('/tmp/testing.sqlite', 'local', false));
    }

    public function test_production_verrouillee_meme_avec_le_bypass(): void
    {
        $this->assertTrue(DestructiveCommandGuard::shouldProhibit('weklo', 'production', true));
        $this->assertTrue(DestructiveCommandGuard::shouldProhibit('testing', 'production', true));
    }

    public function test_une_base_dont_le_nom_contient_testing_sans_commencer_par_nest_pas_une_base_de_test(): void
    {
        $this->assertTrue(DestructiveCommandGuard::shouldProhibit('weklo_testing', 'local', false));
        $this->assertFalse(DestructiveCommandGuard::isTestDatabase('prod_testing'));
    }
}

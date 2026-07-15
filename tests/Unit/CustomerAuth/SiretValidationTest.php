<?php

declare(strict_types=1);

namespace Tests\Unit\CustomerAuth;

use PHPUnit\Framework\TestCase;
use Pko\CustomerAuth\Sirene\SireneClient;

class SiretValidationTest extends TestCase
{
    public function test_valid_siret_is_accepted(): void
    {
        // SIRET réel (siège Danone) — clé de Luhn correcte.
        $this->assertTrue(SireneClient::validateSiret('73282932000074'));
    }

    public function test_valid_siret_with_spaces_is_accepted(): void
    {
        // La saisie avec espaces doit être normalisée puis validée.
        $this->assertTrue(SireneClient::validateSiret('981 043 979 00021'));
        $this->assertTrue(SireneClient::validateSiret('98104397900021'));
    }

    public function test_invalid_luhn_is_rejected(): void
    {
        $this->assertFalse(SireneClient::validateSiret('12345678900000'));
    }

    public function test_wrong_length_is_rejected(): void
    {
        $this->assertFalse(SireneClient::validateSiret('123'));
        // 9 chiffres = SIREN, pas un SIRET.
        $this->assertFalse(SireneClient::validateSiret('981 043 979'));
    }
}

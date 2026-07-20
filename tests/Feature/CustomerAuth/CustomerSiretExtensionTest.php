<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Filament\Extensions\CustomerSiretExtension;
use Pko\CustomerAuth\Sirene\SireneClient;
use Pko\CustomerAuth\Sirene\SireneResult;
use Pko\CustomerAuth\Sirene\Status;
use Tests\TestCase;

class CustomerSiretExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function mockSirene(SireneResult $result): void
    {
        $mock = $this->createMock(SireneClient::class);
        $mock->method('verify')->willReturn($result);
        $this->app->instance(SireneClient::class, $mock);
    }

    private function record(array $meta): Customer
    {
        $customer = new Customer;
        $customer->meta = $meta;

        return $customer;
    }

    public function test_beforefill_charge_le_siret_depuis_meta(): void
    {
        $ext = new CustomerSiretExtension;

        $data = $ext->beforeFill(['meta' => ['siret' => '98104397900021', 'activity' => 'Portails']]);

        $this->assertSame('98104397900021', $data['siret']);
    }

    public function test_beforeupdate_reverifie_et_met_a_jour_si_siret_change(): void
    {
        $this->mockSirene(new SireneResult(
            status: Status::Active,
            siret: '98104397900021',
            raisonSociale: 'NOUVELLE RAISON',
            nafCode: '43.21A',
            addressLine1: '5 RUE NEUVE',
            postcode: '75010',
            city: 'PARIS',
        ));

        $ext = new CustomerSiretExtension;
        $record = $this->record(['siret' => '00000000000000', 'activity' => 'Portails', 'phone' => '0102030405']);

        // '98104397900021' est un SIRET Luhn-valide (cf. autres tests CustomerAuth).
        $data = $ext->beforeUpdate(['siret' => '981 043 979 00021'], $record);

        // Champ virtuel retiré, données dérivées rafraîchies.
        $this->assertArrayNotHasKey('siret', $data);
        $this->assertSame('43.21A', $data['naf_code']);
        $this->assertSame('active', $data['sirene_status']);
        $this->assertNotNull($data['sirene_verified_at']);
        // meta fusionné : nouveau SIRET + clés existantes préservées.
        $this->assertSame('98104397900021', $data['meta']['siret']);
        $this->assertSame('Portails', $data['meta']['activity']);
        $this->assertSame('0102030405', $data['meta']['phone']);
        $this->assertSame('PARIS', $data['meta']['sirene_address']['city']);
    }

    public function test_beforeupdate_siret_inchange_ne_reverifie_pas(): void
    {
        // Aucun mock : si verify() était appelé, le vrai client tenterait un appel réseau.
        $ext = new CustomerSiretExtension;
        $record = $this->record(['siret' => '98104397900021']);

        $data = $ext->beforeUpdate(['siret' => '98104397900021'], $record);

        $this->assertArrayNotHasKey('siret', $data);
        $this->assertArrayNotHasKey('naf_code', $data);
        $this->assertArrayNotHasKey('sirene_status', $data);
    }

    public function test_beforeupdate_siret_invalide_leve_une_erreur(): void
    {
        $ext = new CustomerSiretExtension;
        $record = $this->record(['siret' => '98104397900021']);

        $this->expectException(ValidationException::class);
        $ext->beforeUpdate(['siret' => '12345678901234'], $record);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Pko\CustomerAuth\Actions\RegisterProCustomer;
use Pko\CustomerAuth\Mail\CustomerRegisteredMail;
use Pko\CustomerAuth\Sirene\SireneClient;
use Pko\CustomerAuth\Sirene\SireneResult;
use Pko\CustomerAuth\Sirene\Status;
use Tests\TestCase;

class RegisterProCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function mockSireneActive(string $siret = '98104397900021'): void
    {
        $result = new SireneResult(
            status: Status::Active,
            siret: $siret,
            raisonSociale: 'ACME SAS',
            nafCode: '43.21A',
            addressLine1: '10 RUE DE LA PAIX',
            postcode: '75002',
            city: 'PARIS',
        );

        $mock = $this->createMock(SireneClient::class);
        $mock->method('verify')->willReturn($result);

        $this->app->instance(SireneClient::class, $mock);
    }

    private function defaultData(array $overrides = []): array
    {
        return array_merge([
            'siret' => '98104397900021',
            'email' => 'pro@example.test',
            'password' => 'secret123',
            'street' => '12 avenue des Roses',
            'postcode' => '69001',
            'city' => 'Lyon',
            'country' => 'FR',
        ], $overrides);
    }

    public function test_adresse_postale_est_persistee_sur_le_customer(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        $customer = Customer::find($result['customer']->id);
        $this->assertSame('12 avenue des Roses', $customer->pko_street);
        $this->assertSame('69001', $customer->pko_postcode);
        $this->assertSame('Lyon', $customer->pko_city);
        $this->assertSame('FR', $customer->pko_country);
    }

    public function test_country_par_defaut_fr_si_absent(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $data = $this->defaultData();
        unset($data['country']);
        $result = app(RegisterProCustomer::class)->handle($data);

        $this->assertSame('FR', Customer::find($result['customer']->id)->pko_country);
    }

    public function test_email_confirmation_est_envoye(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        Mail::assertSent(CustomerRegisteredMail::class, function (CustomerRegisteredMail $mail) use ($result) {
            return $mail->hasTo($result['user']->email)
                && $mail->customer->id === $result['customer']->id;
        });
    }

    public function test_email_reste_non_verifie_a_la_creation(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        // L'e-mail doit être confirmé via le lien signé du mail de bienvenue.
        $this->assertNull($result['user']->email_verified_at);
        $this->assertFalse($result['user']->hasVerifiedEmail());
    }

    public function test_siret_actif_active_le_compte_pro(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        $this->assertSame('active', Customer::find($result['customer']->id)->pko_status);
    }

    public function test_groupe_pro_installateurs_est_attache(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        $handles = $result['customer']->customerGroups()->pluck('handle')->toArray();
        $this->assertContains('installateurs', $handles, 'Le groupe "installateurs" doit être attaché au nouveau client pro.');
    }

    public function test_groupe_attache_correspond_au_groupe_en_base(): void
    {
        $group = CustomerGroup::where('handle', (string) config('customer-auth.default_customer_group_handle', 'installateurs'))->first();

        $this->assertNotNull(
            $group,
            'Le groupe "'.config('customer-auth.default_customer_group_handle', 'installateurs').'" doit exister en base (PkoCustomerGroupSeeder).'
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\SireneConfig;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Pko\CustomerAuth\Sirene\SireneClient;
use Pko\CustomerAuth\Sirene\Status;
use Pko\Secrets\Facades\Secrets;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

class SireneConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_page_renders_for_authenticated_staff(): void
    {
        $staff = Staff::create([
            'first_name' => 'Sirene',
            'last_name' => 'Admin',
            'email' => 'sirene-test@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');

        $this->get('/admin/sirene-config')
            ->assertOk()
            ->assertSee('Vérification SIRET')
            ->assertSee('Clés API INSEE');
    }

    public function test_save_persists_enabled_flag_and_database_secrets(): void
    {
        Livewire::test(SireneConfig::class)
            ->set('data.enabled', true)
            ->set('data.secrets_source', 'db')
            ->set('data.secrets.consumer_key', 'MY_KEY')
            ->set('data.secrets.consumer_secret', 'MY_SECRET')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue((bool) Setting::get('sirene.enabled'));
        $this->assertSame('db', Secrets::source('insee'));
        $this->assertSame('MY_KEY', Secrets::get('insee', 'consumer_key'));
        $this->assertSame('MY_SECRET', Secrets::get('insee', 'consumer_secret'));
    }

    public function test_client_is_disabled_when_setting_is_off(): void
    {
        Http::fake();
        config([
            'customer-auth.sirene.consumer_key' => 'k',
            'customer-auth.sirene.consumer_secret' => 's',
        ]);
        Setting::set('sirene.enabled', false);

        $this->app->forgetInstance(SireneClient::class);
        $result = app(SireneClient::class)->verify('12345678901234');

        $this->assertSame(Status::Pending, $result->status);
        Http::assertNothingSent();
    }

    public function test_client_verifies_when_enabled_and_keys_present(): void
    {
        Http::fake([
            'api.insee.fr/token' => Http::response(['access_token' => 'tok'], 200),
            '*/siret/*' => Http::response([
                'etablissement' => [
                    'etatAdministratifEtablissement' => 'A',
                    'uniteLegale' => ['denominationUniteLegale' => 'ACME SARL'],
                    'adresseEtablissement' => [],
                ],
            ], 200),
        ]);
        config([
            'customer-auth.sirene.base_url' => 'https://api.insee.fr/entreprises/sirene/V3.11',
            'customer-auth.sirene.consumer_key' => 'k',
            'customer-auth.sirene.consumer_secret' => 's',
        ]);
        Setting::set('sirene.enabled', true);

        $this->app->forgetInstance(SireneClient::class);
        $result = app(SireneClient::class)->verify('12345678901234');

        $this->assertSame(Status::Active, $result->status);
        $this->assertSame('ACME SARL', $result->raisonSociale);
    }
}

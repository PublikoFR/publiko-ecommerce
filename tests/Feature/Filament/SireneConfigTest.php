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
            ->assertSee('Clé API INSEE');
    }

    public function test_save_persists_enabled_flag_and_database_secrets(): void
    {
        Livewire::test(SireneConfig::class)
            ->set('data.enabled', true)
            ->set('data.secrets_source', 'db')
            ->set('data.secrets.api_key', 'MY_API_KEY')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue((bool) Setting::get('sirene.enabled'));
        $this->assertSame('db', Secrets::source('insee'));
        $this->assertSame('MY_API_KEY', Secrets::get('insee', 'api_key'));
    }

    public function test_client_is_disabled_when_setting_is_off(): void
    {
        Http::fake();
        config(['customer-auth.sirene.api_key' => 'k']);
        Setting::set('sirene.enabled', false);

        $this->app->forgetInstance(SireneClient::class);
        $result = app(SireneClient::class)->verify('12345678901234');

        $this->assertSame(Status::Pending, $result->status);
        Http::assertNothingSent();
    }

    public function test_client_verifies_with_api_key_header_when_enabled(): void
    {
        Http::fake([
            '*/siret/*' => Http::response([
                'etablissement' => [
                    'periodesEtablissement' => [
                        ['dateFin' => null, 'etatAdministratifEtablissement' => 'A'],
                    ],
                    'uniteLegale' => ['denominationUniteLegale' => 'ACME SARL'],
                    'adresseEtablissement' => [],
                ],
            ], 200),
        ]);
        config([
            'customer-auth.sirene.base_url' => 'https://api.insee.fr/api-sirene/3.11',
            'customer-auth.sirene.api_key' => 'MY_API_KEY',
        ]);
        Setting::set('sirene.enabled', true);

        $this->app->forgetInstance(SireneClient::class);
        $result = app(SireneClient::class)->verify('12345678901234');

        $this->assertSame(Status::Active, $result->status);
        $this->assertSame('ACME SARL', $result->raisonSociale);

        // La clé API est bien transmise en en-tête (pas d'OAuth / token).
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api-sirene/3.11/siret/12345678901234')
                && $request->hasHeader('X-INSEE-Api-Key-Integration', 'MY_API_KEY');
        });
    }
}

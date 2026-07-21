<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Pko\CustomerAuth\Livewire\RegisterPage;
use Pko\CustomerAuth\Sirene\SireneClient;
use Tests\TestCase;

class RegisterPagePrefillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        // Le composant résout SireneClient via le conteneur : on force une
        // instance « enabled » pour exercer le chemin de préremplissage réel.
        $this->app->singleton(SireneClient::class, fn () => new SireneClient(
            baseUrl: 'https://api.insee.fr/api-sirene/3.11',
            apiKey: 'test-key',
            enabled: true,
        ));
    }

    /** @return array<string, mixed> */
    private function activeEtablissement(): array
    {
        return [
            'etablissement' => [
                'siret' => '98104397900021',
                'uniteLegale' => [
                    'denominationUniteLegale' => 'ACME SAS',
                    'activitePrincipaleUniteLegale' => '43.21A',
                ],
                'adresseEtablissement' => [
                    'numeroVoieEtablissement' => '10',
                    'typeVoieEtablissement' => 'RUE',
                    'libelleVoieEtablissement' => 'DE LA PAIX',
                    'codePostalEtablissement' => '75002',
                    'libelleCommuneEtablissement' => 'PARIS',
                ],
                'periodesEtablissement' => [
                    [
                        'dateFin' => null,
                        'etatAdministratifEtablissement' => 'A',
                        'activitePrincipaleEtablissement' => '43.21A',
                    ],
                ],
            ],
        ];
    }

    public function test_valid_siret_prefills_company_fields_server_side(): void
    {
        Http::fake([
            '*/siret/98104397900021' => Http::response($this->activeEtablissement(), 200),
        ]);

        Livewire::test(RegisterPage::class)
            ->set('siret', '98104397900021')
            ->assertSet('sireneVerified', true)
            ->assertSet('companyName', 'ACME SAS')
            // Le code NAF n'est plus affiché dans le formulaire mais reste renseigné
            // côté serveur pour être persisté sur le customer.
            ->assertSet('activity', '43.21A')
            ->assertSet('street', '10 RUE DE LA PAIX')
            ->assertSet('postcode', '75002')
            ->assertSet('city', 'PARIS');
    }

    public function test_prefill_does_not_overwrite_user_input(): void
    {
        Http::fake([
            '*/siret/98104397900021' => Http::response($this->activeEtablissement(), 200),
        ]);

        Livewire::test(RegisterPage::class)
            ->set('city', 'Lyon')
            ->set('siret', '98104397900021')
            ->assertSet('companyName', 'ACME SAS')
            ->assertSet('city', 'Lyon');
    }

    /**
     * Le champ Pays a été retiré du formulaire : la validation du SIRET auprès de
     * l'INSEE garantit déjà une entreprise française. Le pays reste forcé à « FR »
     * côté action (cf. RegisterProCustomerTest).
     */
    public function test_country_field_is_absent_from_the_form(): void
    {
        Livewire::test(RegisterPage::class)
            ->assertDontSee('Pays');
    }
}

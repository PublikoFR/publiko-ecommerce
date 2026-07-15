<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use Illuminate\Support\Facades\Http;
use Pko\CustomerAuth\Sirene\SireneClient;
use Pko\CustomerAuth\Sirene\Status;
use Tests\TestCase;

class SireneVerifyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Aucune requête réseau réelle ne doit fuir vers l'API INSEE.
        Http::preventStrayRequests();
    }

    private function client(): SireneClient
    {
        return new SireneClient(
            baseUrl: 'https://api.insee.fr/api-sirene/3.11',
            apiKey: 'test-key',
            enabled: true,
        );
    }

    /**
     * Réponse Sirene v3 réaliste : etatAdministratifEtablissement et
     * activitePrincipaleEtablissement vivent dans periodesEtablissement
     * (variables historisées), pas à la racine de etablissement.
     *
     * @return array<string, mixed>
     */
    private function activeEtablissement(): array
    {
        return [
            'etablissement' => [
                'siren' => '981043979',
                'nic' => '00021',
                'siret' => '98104397900021',
                'uniteLegale' => [
                    'denominationUniteLegale' => 'ACME SAS',
                    'activitePrincipaleUniteLegale' => '43.21A',
                    'categorieEntreprise' => 'PME',
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
                        'dateDebut' => '2021-01-01',
                        'etatAdministratifEtablissement' => 'A',
                        'activitePrincipaleEtablissement' => '43.21A',
                    ],
                    [
                        'dateFin' => '2020-12-31',
                        'dateDebut' => '2019-01-01',
                        'etatAdministratifEtablissement' => 'A',
                        'activitePrincipaleEtablissement' => '43.21B',
                    ],
                ],
            ],
        ];
    }

    public function test_active_establishment_is_parsed_from_current_period(): void
    {
        Http::fake([
            '*/siret/98104397900021' => Http::response($this->activeEtablissement(), 200),
        ]);

        $result = $this->client()->verify('98104397900021');

        $this->assertSame(Status::Active, $result->status);
        $this->assertTrue($result->isActive());
        $this->assertSame('ACME SAS', $result->raisonSociale);
        // NAF lu depuis la période courante (dateFin null), pas l'ancienne.
        $this->assertSame('43.21A', $result->nafCode);
        $this->assertSame('10 RUE DE LA PAIX', $result->addressLine1);
        $this->assertSame('75002', $result->postcode);
        $this->assertSame('PARIS', $result->city);
    }

    public function test_closed_establishment_is_inactive(): void
    {
        $body = $this->activeEtablissement();
        $body['etablissement']['periodesEtablissement'][0]['etatAdministratifEtablissement'] = 'F';

        Http::fake([
            '*/siret/98104397900021' => Http::response($body, 200),
        ]);

        $result = $this->client()->verify('98104397900021');

        $this->assertSame(Status::Inactive, $result->status);
    }

    public function test_unknown_siret_404_is_inactive(): void
    {
        Http::fake([
            '*/siret/*' => Http::response(['header' => ['statut' => 404]], 404),
        ]);

        $result = $this->client()->verify('00000000000000');

        $this->assertSame(Status::Inactive, $result->status);
    }

    public function test_api_error_falls_back_to_pending(): void
    {
        Http::fake([
            '*/siret/*' => Http::response('boom', 500),
        ]);

        $result = $this->client()->verify('98104397900021');

        $this->assertSame(Status::Pending, $result->status);
    }
}

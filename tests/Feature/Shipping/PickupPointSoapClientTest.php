<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingChronopost\Exceptions\PickupPointException;
use Pko\ShippingChronopost\Services\PickupPointSoapClient;
use Pko\ShippingCommon\Dto\PickupPoint;
use SoapClient;
use SoapFault;
use Tests\TestCase;

/**
 * Tests for PickupPointSoapClient.
 *
 * SoapClient is injected via constructor (3rd arg) for full isolation —
 * no real SOAP call, no WSDL fetch.
 */
class PickupPointSoapClientTest extends TestCase
{
    private function makeFakeResponse(array $points = [], string $errorCode = '0', string $errorMessage = ''): object
    {
        $rawPoints = array_map(function (array $p): object {
            $obj = new \stdClass;
            $obj->identifiant = $p['id'] ?? 'PR999';
            $obj->nom = $p['name'] ?? 'Point Test';
            $obj->adresse1 = $p['address1'] ?? '1 rue Test';
            $obj->codePostal = $p['postcode'] ?? '75001';
            $obj->localite = $p['city'] ?? 'Paris';
            $obj->codePays = $p['country_code'] ?? 'FR';
            $obj->distanceEnMetre = isset($p['distance_m']) ? (string) $p['distance_m'] : null;
            $obj->coordGeolocalisationLatitude = $p['lat'] ?? null;
            $obj->coordGeolocalisationLongitude = $p['lon'] ?? null;
            $obj->listeHoraireOuverture = null;

            return $obj;
        }, $points);

        $payload = new \stdClass;
        $payload->errorCode = $errorCode;
        $payload->errorMessage = $errorMessage;
        $payload->listePointRelais = count($rawPoints) === 1 ? $rawPoints[0] : $rawPoints;

        $response = new \stdClass;
        $response->return = $payload;

        return $response;
    }

    private function makeSoapClientMock(mixed $returnValue): SoapClient&MockInterface
    {
        $mock = Mockery::mock(SoapClient::class);
        $mock->expects('recherchePointChronopostInter')
            ->once()
            ->andReturn($returnValue);

        return $mock;
    }

    public function test_parse_un_point_unique_correctement(): void
    {
        $response = $this->makeFakeResponse([
            ['id' => 'PR001', 'name' => 'Tabac', 'address1' => '1 rue A', 'postcode' => '75001', 'city' => 'Paris', 'country_code' => 'FR', 'distance_m' => 400, 'lat' => '48,8698', 'lon' => '2,3304'],
        ]);

        $soapMock = $this->makeSoapClientMock($response);

        $client = new PickupPointSoapClient(
            credentials: ['account' => 'ACC', 'password' => 'PWD'],
            wsdl: null,
            client: $soapMock,
        );

        $result = $client->search('75001');

        $this->assertCount(1, $result);
        $this->assertSame('PR001', $result[0]['id']);
        $this->assertSame('Tabac', $result[0]['name']);
        $this->assertSame(0.4, $result[0]['distance_km']);
        // Virgule → point pour lat/lon
        $this->assertSame(48.8698, $result[0]['latitude']);
        $this->assertSame(2.3304, $result[0]['longitude']);
    }

    public function test_parse_liste_multiple_de_points(): void
    {
        $response = $this->makeFakeResponse([
            ['id' => 'PR001', 'name' => 'A'],
            ['id' => 'PR002', 'name' => 'B'],
        ]);

        $soapMock = $this->makeSoapClientMock($response);

        $client = new PickupPointSoapClient(
            credentials: ['account' => 'ACC', 'password' => 'PWD'],
            wsdl: null,
            client: $soapMock,
        );

        $result = $client->search('75002');
        $this->assertCount(2, $result);
    }

    public function test_leve_exception_sur_code_erreur_api(): void
    {
        $response = new \stdClass;
        $payload = new \stdClass;
        $payload->errorCode = '99';
        $payload->errorMessage = 'Service unavailable';
        $payload->listePointRelais = [];
        $response->return = $payload;

        $soapMock = $this->makeSoapClientMock($response);

        $client = new PickupPointSoapClient(
            credentials: ['account' => 'ACC', 'password' => 'PWD'],
            wsdl: null,
            client: $soapMock,
        );

        $this->expectException(PickupPointException::class);
        $this->expectExceptionMessage('[99]');
        $client->search('75001');
    }

    public function test_leve_exception_sur_soap_fault(): void
    {
        $soapMock = Mockery::mock(SoapClient::class);
        $soapMock->expects('recherchePointChronopostInter')
            ->andThrow(new SoapFault('Server', 'Connection refused'));

        $client = new PickupPointSoapClient(
            credentials: ['account' => 'ACC', 'password' => 'PWD'],
            wsdl: null,
            client: $soapMock,
        );

        $this->expectException(PickupPointException::class);
        $this->expectExceptionMessage('SOAP fault');
        $client->search('75001');
    }

    public function test_leve_exception_si_credentials_manquants(): void
    {
        $client = new PickupPointSoapClient(credentials: []);

        $this->expectException(PickupPointException::class);
        $this->expectExceptionMessage('missing account credentials');
        $client->search('75001');
    }

    /**
     * Le WS ne valide pas par schéma : il répond par des erreurs métier. Ces trois
     * paramètres ont été trouvés empiriquement et le WS échoue silencieusement si
     * l'un manque — d'où cette garde.
     *
     * - `type` vide  → 300 « Il faut que le type ou le pudoType soient renseignés »
     * - `service` vide → 300 « service [] incorrect »
     * - `city` vide  → 700 « The parameter named 'city' is required »
     */
    public function test_envoie_les_parametres_obligatoires_du_ws(): void
    {
        $captured = null;
        $mock = Mockery::mock(SoapClient::class);
        $mock->expects('recherchePointChronopostInter')
            ->once()
            ->andReturnUsing(function (array $args) use (&$captured) {
                $captured = $args;

                return $this->makeFakeResponse();
            });

        $client = new PickupPointSoapClient(
            credentials: ['account' => 'A', 'password' => 'P'],
            client: $mock,
        );
        $client->search('34500');

        $this->assertSame('P', $captured['type']);
        $this->assertSame('L', $captured['service']);
        $this->assertSame('34500', $captured['zipCode']);
        // City absente → repli sur le code postal, jamais une chaîne vide.
        $this->assertSame('34500', $captured['city']);
    }

    /**
     * Chez Chronopost la ville prime sur le code postal : une ville explicite doit
     * être transmise telle quelle (c'est à l'appelant de garantir sa cohérence).
     */
    public function test_transmet_la_ville_quand_elle_est_fournie(): void
    {
        $captured = null;
        $mock = Mockery::mock(SoapClient::class);
        $mock->expects('recherchePointChronopostInter')
            ->once()
            ->andReturnUsing(function (array $args) use (&$captured) {
                $captured = $args;

                return $this->makeFakeResponse();
            });

        $client = new PickupPointSoapClient(
            credentials: ['account' => 'A', 'password' => 'P'],
            client: $mock,
        );
        $client->search('34500', 'FR', null, 'Béziers');

        $this->assertSame('Béziers', $captured['city']);
    }

    /**
     * Point `8339S` tel que décodé par SoapClient depuis la réponse d'exemple
     * fournie par Chronopost (« Requête-Réponse recherchePointChronopostInter
     * Chrono RELAIS 13H.txt ») : jours renvoyés du samedi (6) au lundi (1),
     * samedi matin seul, lundi–vendredi en deux plages. Chaque plage
     * `listeHoraireOuverture` est un objet quand il n'y en a qu'une (samedi).
     */
    private function realPoint8339S(): object
    {
        $slot = fn (string $start, string $end): object => (object) ['debut' => $start, 'fin' => $end];

        $days = [(object) [
            'horairesAsString' => '08:15-12:00',
            'jour' => 6,
            'listeHoraireOuverture' => $slot('08:15', '12:00'),
        ]];
        foreach ([5, 4, 3, 2, 1] as $day) {
            $days[] = (object) [
                'horairesAsString' => '08:15-12:00 12:00-17:00',
                'jour' => $day,
                'listeHoraireOuverture' => [$slot('08:15', '12:00'), $slot('12:00', '17:00')],
            ];
        }

        return (object) [
            'accesPersonneMobiliteReduite' => true,
            'actif' => true,
            'adresse1' => '5 rue père dieuzaide',
            'adresse2' => '',
            'adresse3' => '',
            'codePays' => 'FR',
            'codePostal' => '33000',
            'coordGeolocalisationLatitude' => '44.83916660000',
            'coordGeolocalisationLongitude' => '-0.58364120000',
            'distanceEnMetre' => 218,
            'identifiant' => '8339S',
            'indiceDeLocalisation' => '',
            'listeHoraireOuverture' => $days,
            'localite' => 'BORDEAUX',
            'nom' => 'LA POSTE CARREPRO MERIADECK',
            'poidsMaxi' => 20,
            'typeDePoint' => 'P',
            'urlGoogleMaps' => 'http://maps.google.fr/maps?q=44.83916660000,-0.58364120000',
        ];
    }

    /**
     * @param  object|list<object>  $points
     */
    private function wrapResponse(object|array $points, int $qualite = 2): object
    {
        return (object) ['return' => (object) [
            'errorCode' => 0,
            'errorMessage' => 'Code retour OK',
            'listePointRelais' => $points,
            'qualiteReponse' => $qualite,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchWith(object $response): array
    {
        $client = new PickupPointSoapClient(
            credentials: ['account' => 'ACC', 'password' => 'PWD'],
            client: $this->makeSoapClientMock($response),
        );

        return $client->search('33000');
    }

    public function test_horaires_reels_du_point_8339s(): void
    {
        $result = $this->searchWith($this->wrapResponse($this->realPoint8339S()));

        $this->assertCount(1, $result);
        $point = $result[0];

        $this->assertSame('8339S', $point['id']);
        $this->assertSame(0.22, $point['distance_km']);
        $this->assertSame(20.0, $point['max_weight_kg']);

        // Trié du lundi au samedi, dimanche absent = fermé (rien d'inventé).
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($point['opening_schedule'], 'day'));
        $this->assertSame(['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'], array_column($point['opening_schedule'], 'label'));
        $this->assertSame('08:15-12:00 12:00-17:00', $point['opening_schedule'][0]['hours']);
        $this->assertSame('08:15-12:00', $point['opening_schedule'][5]['hours']);

        $this->assertSame('Lun–Ven 08:15-12:00 12:00-17:00 · Sam 08:15-12:00', $point['opening_hours']);
    }

    public function test_jours_non_consecutifs_ne_sont_pas_regroupes(): void
    {
        $point = $this->realPoint8339S();
        // Mercredi fermé : Lun–Mar puis Jeu–Ven, pas de « Lun–Ven ».
        $point->listeHoraireOuverture = array_values(array_filter(
            $point->listeHoraireOuverture,
            fn (object $d) => $d->jour !== 3,
        ));

        $result = $this->searchWith($this->wrapResponse($point));

        $this->assertSame(
            'Lun–Mar 08:15-12:00 12:00-17:00 · Jeu–Ven 08:15-12:00 12:00-17:00 · Sam 08:15-12:00',
            $result[0]['opening_hours'],
        );
    }

    /**
     * SoapClient rend un objet (et non un tableau) quand un seul jour est ouvert.
     * Sans horairesAsString, repli sur les plages debut/fin.
     */
    public function test_un_seul_jour_en_objet_et_repli_sur_debut_fin(): void
    {
        $point = $this->realPoint8339S();
        $point->listeHoraireOuverture = (object) [
            'jour' => 7,
            'listeHoraireOuverture' => (object) ['debut' => '09:00', 'fin' => '12:30'],
        ];

        $result = $this->searchWith($this->wrapResponse([$point, $this->realPoint8339S()]));

        $this->assertCount(2, $result);
        $this->assertSame([['day' => 7, 'label' => 'Dim', 'hours' => '09:00-12:30']], $result[0]['opening_schedule']);
        $this->assertSame('Dim 09:00-12:30', $result[0]['opening_hours']);
    }

    /**
     * Aucun horaire renvoyé = aucune contrainte horaire (consigne libre-service,
     * doc §2.4.2.b) : signalé comme accès libre, pas comme horaires inconnus.
     */
    public function test_point_sans_horaires_est_en_acces_libre(): void
    {
        $point = $this->realPoint8339S();
        unset($point->listeHoraireOuverture);

        $result = $this->searchWith($this->wrapResponse($point));

        $this->assertSame([], $result[0]['opening_schedule']);
        $this->assertNull($result[0]['opening_hours']);

        $dto = PickupPoint::fromArray($result[0]);
        $this->assertTrue($dto->hasFreeAccess());
        $this->assertTrue($dto->toArray()['free_access']);
    }

    /**
     * Horaires présents mais illisibles : on n'annonce PAS un accès libre.
     */
    public function test_horaires_illisibles_ne_valent_pas_acces_libre(): void
    {
        $point = $this->realPoint8339S();
        $point->listeHoraireOuverture = (object) ['jour' => 9, 'horairesAsString' => '08:00-12:00'];

        $result = $this->searchWith($this->wrapResponse($point));

        $this->assertNull($result[0]['opening_schedule']);
        $this->assertNull($result[0]['opening_hours']);
        $this->assertFalse(PickupPoint::fromArray($result[0])->hasFreeAccess());
    }

    public function test_qualite_reponse_zero_est_ignoree(): void
    {
        $this->expectException(PickupPointException::class);
        $this->expectExceptionMessage('qualiteReponse=0');

        $this->searchWith($this->wrapResponse($this->realPoint8339S(), qualite: 0));
    }

    public function test_qualite_reponse_un_est_acceptee(): void
    {
        $this->assertCount(1, $this->searchWith($this->wrapResponse($this->realPoint8339S(), qualite: 1)));
    }

    public function test_point_inactif_est_exclu(): void
    {
        $inactive = $this->realPoint8339S();
        $inactive->actif = false;
        $inactive->identifiant = 'OFF01';

        $result = $this->searchWith($this->wrapResponse([$inactive, $this->realPoint8339S()]));

        $this->assertSame(['8339S'], array_column($result, 'id'));
    }

    /**
     * Requête alignée sur la doc VL3.25.10.10 §2.4.2.a : productCode 86 (Chrono
     * Relais 13H), shippingDate JJ/MM/AAAA du jour, poids en grammes.
     */
    public function test_requete_conforme_a_la_doc(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');

        $captured = [];
        $mock = Mockery::mock(SoapClient::class);
        $mock->expects('recherchePointChronopostInter')
            ->twice()
            ->andReturnUsing(function (array $args) use (&$captured) {
                $captured[] = $args;

                return $this->makeFakeResponse();
            });

        $client = new PickupPointSoapClient(
            credentials: ['account' => 'A', 'password' => 'P'],
            client: $mock,
        );
        $client->search('33000', 'FR', null, null, 5250);
        // Hors format du WS (5 chiffres max) → non transmis plutôt qu'une erreur 321.
        $client->search('33000', 'FR', null, null, 150000);

        $this->assertSame('86', $captured[0]['productCode']);
        $this->assertSame('25/09/2026', $captured[0]['shippingDate']);
        $this->assertSame(5250, $captured[0]['weight']);
        $this->assertSame('', $captured[1]['weight']);
        // Découvertes empiriques §5.13.B inchangées.
        $this->assertSame('P', $captured[0]['type']);
        $this->assertSame('L', $captured[0]['service']);

        Carbon::setTestNow();
    }
}

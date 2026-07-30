<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Mockery;
use Mockery\MockInterface;
use Pko\ShippingChronopost\Exceptions\PickupPointException;
use Pko\ShippingChronopost\Services\PickupPointSoapClient;
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
}

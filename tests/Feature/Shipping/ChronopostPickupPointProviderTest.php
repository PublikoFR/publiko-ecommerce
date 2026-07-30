<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Pko\ShippingChronopost\Exceptions\PickupPointException;
use Pko\ShippingChronopost\Services\ChronopostPickupPointProvider;
use Pko\ShippingChronopost\Services\PickupPointSoapClient;
use Pko\ShippingCommon\Contracts\PickupPointProvider;
use Pko\ShippingCommon\Dto\PickupPoint;
use Tests\TestCase;

/**
 * Tests for ChronopostPickupPointProvider.
 *
 * PickupPointSoapClient is mocked — the SDK SOAP call is not exercised.
 * Piège connu repo : assertSee() Livewire échappe les valeurs — ici pas de Livewire,
 * donc pas concerné.
 */
class ChronopostPickupPointProviderTest extends TestCase
{
    private function makeSoapClientMock(): PickupPointSoapClient&MockInterface
    {
        return Mockery::mock(PickupPointSoapClient::class);
    }

    public function test_retourne_points_depuis_soap(): void
    {
        $rawPoints = [
            [
                'id' => 'PR001',
                'name' => 'Tabac du Centre',
                'address1' => '1 rue de la Paix',
                'postcode' => '75001',
                'city' => 'Paris',
                'country_code' => 'FR',
                'distance_km' => 0.4,
                'latitude' => 48.8698,
                'longitude' => 2.3304,
                'opening_hours' => 'Lun: 08:00-19:00',
            ],
        ];

        $soapClient = $this->makeSoapClientMock();
        $soapClient->expects('search')
            ->once()
            ->with('75001', 'FR', null)
            ->andReturn($rawPoints);

        $provider = new ChronopostPickupPointProvider($soapClient);
        $result = $provider->search('75001', 'FR', null);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PickupPoint::class, $result[0]);
        $this->assertSame('PR001', $result[0]->id);
        $this->assertSame('Tabac du Centre', $result[0]->name);
        $this->assertSame(48.8698, $result[0]->latitude);
        $this->assertSame(2.3304, $result[0]->longitude);
    }

    public function test_retourne_tableau_vide_sur_erreur_soap(): void
    {
        $soapClient = $this->makeSoapClientMock();
        $soapClient->expects('search')
            ->once()
            ->andThrow(new PickupPointException('SOAP fault: connection timeout'));

        $provider = new ChronopostPickupPointProvider($soapClient);
        $result = $provider->search('13001', 'FR', null);

        $this->assertSame([], $result);
    }

    public function test_cache_evite_double_appel_soap(): void
    {
        Cache::flush();

        $rawPoints = [
            [
                'id' => 'PR002',
                'name' => 'Presse du Marché',
                'address1' => '5 place du Marché',
                'postcode' => '69001',
                'city' => 'Lyon',
                'country_code' => 'FR',
                'distance_km' => null,
                'latitude' => null,
                'longitude' => null,
                'opening_hours' => null,
            ],
        ];

        $soapClient = $this->makeSoapClientMock();
        // Doit être appelé UNE seule fois — le second appel doit venir du cache
        $soapClient->expects('search')
            ->once()
            ->andReturn($rawPoints);

        $provider = new ChronopostPickupPointProvider($soapClient);

        $result1 = $provider->search('69001', 'FR', null);
        $result2 = $provider->search('69001', 'FR', null);

        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);
        $this->assertSame('PR002', $result1[0]->id);
    }

    public function test_erreur_soap_non_mise_en_cache(): void
    {
        Cache::flush();

        $soapClient = $this->makeSoapClientMock();
        // Deux erreurs → deux appels SOAP (les erreurs ne sont pas cachées)
        $soapClient->expects('search')
            ->twice()
            ->andThrow(new PickupPointException('timeout'));

        $provider = new ChronopostPickupPointProvider($soapClient);

        $r1 = $provider->search('33000', 'FR', null);
        $r2 = $provider->search('33000', 'FR', null);

        $this->assertSame([], $r1);
        $this->assertSame([], $r2);
    }

    /**
     * Régression F1 : PickupPointSoapClient n'était pas importé ni enregistré dans
     * ShippingChronopostServiceProvider. Le conteneur levait une erreur dès la première
     * résolution réelle de PickupPointProvider. Les tests unitaires masquaient le bug
     * parce qu'ils injectaient le mock directement dans le constructeur.
     * Ce test résout PickupPointProvider VIA LE CONTENEUR pour détecter ce type d'erreur.
     */
    public function test_pickup_point_provider_resolvable_via_conteneur(): void
    {
        // Remplace PickupPointSoapClient dans le conteneur par un mock pour éviter
        // tout appel SOAP réel lors de la résolution.
        $this->app->singleton(PickupPointSoapClient::class, fn () => $this->makeSoapClientMock());

        $provider = $this->app->make(PickupPointProvider::class);

        $this->assertInstanceOf(ChronopostPickupPointProvider::class, $provider);
    }

    public function test_points_sans_id_sont_exclus(): void
    {
        $rawPoints = [
            ['id' => '', 'name' => 'Sans ID', 'address1' => '', 'postcode' => '', 'city' => '', 'country_code' => 'FR', 'distance_km' => null, 'latitude' => null, 'longitude' => null, 'opening_hours' => null],
            ['id' => 'PR003', 'name' => 'Avec ID', 'address1' => '2 rue Test', 'postcode' => '31000', 'city' => 'Toulouse', 'country_code' => 'FR', 'distance_km' => null, 'latitude' => null, 'longitude' => null, 'opening_hours' => null],
        ];

        $soapClient = $this->makeSoapClientMock();
        $soapClient->expects('search')->once()->andReturn($rawPoints);

        $provider = new ChronopostPickupPointProvider($soapClient);
        $result = $provider->search('31000', 'FR', null);

        $this->assertCount(1, $result);
        $this->assertSame('PR003', $result[0]->id);
    }
}

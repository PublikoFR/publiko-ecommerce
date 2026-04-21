<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use Illuminate\Http\Client\Factory as HttpFactory;
use PHPUnit\Framework\TestCase;
use Pko\ShippingCommon\Tracking\LaPosteTrackingClient;
use Pko\ShippingCommon\Tracking\TrackingException;

class LaPosteTrackingClientTest extends TestCase
{
    public function test_missing_api_key_throws(): void
    {
        $http = new HttpFactory;
        $client = new LaPosteTrackingClient(http: $http, apiKey: '');

        $this->expectException(TrackingException::class);
        $this->expectExceptionMessageMatches('/api key missing/i');

        $client->track('8Q12345678901');
    }

    public function test_parses_delivered_response(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*/idships/8Q99999999999*' => $http::response([
                'shipment' => [
                    'idShip' => '8Q99999999999',
                    'event' => [
                        ['date' => '2026-04-20T10:22:00+02:00', 'code' => 'DI1', 'label' => 'Colis livré'],
                        ['date' => '2026-04-20T08:15:00+02:00', 'code' => 'ET_01', 'label' => 'En cours de livraison'],
                    ],
                ],
            ], 200),
        ]);

        $client = new LaPosteTrackingClient(http: $http, apiKey: 'fake-key');
        $status = $client->track('8Q99999999999');

        $this->assertSame('delivered', $status->status);
        $this->assertTrue($status->isDelivered());
        $this->assertCount(2, $status->events);
        $this->assertNotNull($status->deliveredAt);
        $this->assertStringContainsString('8Q99999999999', (string) $status->publicUrl);
    }

    public function test_parses_in_transit_response(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*/idships/*' => $http::response([
                'shipment' => [
                    'event' => [
                        ['date' => '2026-04-20T09:00:00+02:00', 'code' => 'ET_10', 'label' => 'En transit'],
                    ],
                ],
            ], 200),
        ]);

        $client = new LaPosteTrackingClient(http: $http, apiKey: 'fake-key');
        $status = $client->track('AB12345');

        $this->assertSame('in_transit', $status->status);
        $this->assertFalse($status->isDelivered());
        $this->assertFalse($status->isTerminal());
    }

    public function test_404_maps_to_not_found_exception(): void
    {
        $http = new HttpFactory;
        $http->fake(['*/idships/*' => $http::response(['code' => 'NOT_FOUND'], 404)]);

        $client = new LaPosteTrackingClient(http: $http, apiKey: 'fake-key');

        $this->expectException(TrackingException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $client->track('UNKNOWN');
    }

    public function test_5xx_maps_to_unexpected_status(): void
    {
        $http = new HttpFactory;
        $http->fake(['*/idships/*' => $http::response('gateway down', 503)]);

        $client = new LaPosteTrackingClient(http: $http, apiKey: 'fake-key');

        $this->expectException(TrackingException::class);
        $this->expectExceptionMessageMatches('/HTTP 503/');

        $client->track('8Q123');
    }
}

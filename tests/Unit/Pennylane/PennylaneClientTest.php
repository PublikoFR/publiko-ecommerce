<?php

declare(strict_types=1);

namespace Tests\Unit\Pennylane;

use Illuminate\Http\Client\Factory as HttpFactory;
use PHPUnit\Framework\TestCase;
use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Api\Exceptions\PennylaneNotConfiguredException;
use Pko\Pennylane\Api\PennylaneClient;

class PennylaneClientTest extends TestCase
{
    private function makeClient(HttpFactory $http, array $override = []): PennylaneClient
    {
        return new PennylaneClient(
            http: $http,
            config: array_replace_recursive([
                'api_token' => 'test-token',
                'base_url' => 'https://app.pennylane.com/api/external/v2',
                'http' => ['timeout' => 5, 'retry_times' => 1, 'retry_sleep_ms' => 10],
            ], $override),
        );
    }

    public function test_missing_token_throws(): void
    {
        $client = $this->makeClient(new HttpFactory, ['api_token' => null]);

        $this->expectException(PennylaneNotConfiguredException::class);
        $client->get('/me');
    }

    public function test_get_sends_bearer_and_parses_response(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*/me' => $http::response(['id' => 42, 'email' => 'test@example.com'], 200),
        ]);

        $client = $this->makeClient($http);
        $response = $client->get('/me');

        $this->assertSame(42, $response->json('id'));
        $http->assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_failed_response_throws_api_exception(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*' => $http::response(['error' => 'bad'], 422),
        ]);

        $client = $this->makeClient($http);

        try {
            $client->post('/customer_invoices', ['foo' => 'bar']);
            $this->fail('Expected PennylaneApiException');
        } catch (PennylaneApiException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('bad', $e->body['error']);
        }
    }

    public function test_is_configured(): void
    {
        $this->assertFalse($this->makeClient(new HttpFactory, ['api_token' => ''])->isConfigured());
        $this->assertTrue($this->makeClient(new HttpFactory)->isConfigured());
    }
}

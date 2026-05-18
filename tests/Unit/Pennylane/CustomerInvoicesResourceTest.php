<?php

declare(strict_types=1);

namespace Tests\Unit\Pennylane;

use Illuminate\Http\Client\Factory as HttpFactory;
use PHPUnit\Framework\TestCase;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CustomerInvoicesResource;

class CustomerInvoicesResourceTest extends TestCase
{
    private function resource(HttpFactory $http): CustomerInvoicesResource
    {
        $client = new PennylaneClient($http, [
            'api_token' => 'test',
            'base_url' => 'https://app.pennylane.com/api/external/v2',
            'http' => ['timeout' => 5, 'retry_times' => 1, 'retry_sleep_ms' => 10],
        ]);

        return new CustomerInvoicesResource($client);
    }

    public function test_create_invoice_posts_payload(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*/customer_invoices' => $http::response(['id' => 777, 'status' => 'draft', 'invoice_number' => null], 201),
        ]);

        $result = $this->resource($http)->create([
            'customer_id' => 42,
            'date' => '2026-04-22',
            'deadline' => '2026-04-22',
            'currency' => 'EUR',
        ]);

        $this->assertSame(777, $result['id']);
        $this->assertSame('draft', $result['status']);
    }

    public function test_finalize_puts_endpoint(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*/customer_invoices/777/finalize' => $http::response([
                'id' => 777,
                'invoice_number' => 'F20260001',
            ], 200),
        ]);

        $result = $this->resource($http)->finalize(777);

        $this->assertSame('F20260001', $result['invoice_number']);
        $http->assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/customer_invoices/777/finalize'));
    }

    public function test_find_by_external_reference_returns_first_item(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*/customer_invoices*' => $http::response([
                'items' => [['id' => 555, 'external_reference' => 'order_1', 'status' => 'finalized']],
                'has_more' => false,
            ], 200),
        ]);

        $item = $this->resource($http)->findByExternalReference('order_1');

        $this->assertSame(555, $item['id']);
    }

    public function test_find_by_external_reference_returns_null_on_404(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*/customer_invoices*' => $http::response(['error' => 'not found'], 404),
        ]);

        $this->assertNull($this->resource($http)->findByExternalReference('order_404'));
    }
}

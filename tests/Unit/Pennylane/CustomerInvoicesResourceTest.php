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
            '*/customer_invoices' => $http::response(['id' => 777, 'draft' => true, 'invoice_number' => null], 201),
        ]);

        $result = $this->resource($http)->create([
            'customer_id' => 42,
            'date' => '2026-04-22',
            'deadline' => '2026-04-22',
            'currency' => 'EUR',
        ]);

        $this->assertSame(777, $result['id']);
        $this->assertFalse(CustomerInvoicesResource::isFinalized($result));
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
                'items' => [['id' => 555, 'external_reference' => 'order_1', 'draft' => false]],
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

    public function test_finalization_is_read_from_draft_flag(): void
    {
        // v2 : `status` décrit le paiement (upcoming, paid…), jamais « finalized ».
        $this->assertTrue(CustomerInvoicesResource::isFinalized(['draft' => false, 'status' => 'upcoming']));
        $this->assertFalse(CustomerInvoicesResource::isFinalized(['draft' => true, 'status' => 'draft']));
        $this->assertFalse(CustomerInvoicesResource::isFinalized([]));
    }

    public function test_link_credit_note_posts_credit_note_id(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http::response([], 200)]);

        $this->resource($http)->linkCreditNote(777, 888);

        $http->assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/customer_invoices/777/link_credit_note')
            && $request['credit_note_id'] === 888);
    }
}

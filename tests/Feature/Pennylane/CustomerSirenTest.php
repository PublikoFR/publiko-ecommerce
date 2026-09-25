<?php

declare(strict_types=1);

namespace Tests\Feature\Pennylane;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Lunar\Models\Customer;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CustomersResource;
use Pko\Pennylane\Models\PennylaneCustomer;
use Pko\Pennylane\Services\CustomerMapper;
use Tests\TestCase;

class CustomerSirenTest extends TestCase
{
    use RefreshDatabase;

    private Factory $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new Factory;
        $this->http->preventStrayRequests();
        $this->http->fake(['*/company_customers/*' => $this->http::response(['id' => 42])]);
        Http::swap($this->http);
    }

    private function mapper(): CustomerMapper
    {
        return new CustomerMapper(new CustomersResource(new PennylaneClient($this->http, [
            'api_token' => 'test',
            'base_url' => 'https://app.pennylane.com/api/external/v2',
            'http' => ['timeout' => 5, 'retry_times' => 1, 'retry_sleep_ms' => 10],
        ])));
    }

    private function mapping(array $meta, array $snapshot): PennylaneCustomer
    {
        $customer = Customer::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'Acme',
            'meta' => $meta,
        ]);

        return PennylaneCustomer::create([
            'lunar_customer_id' => $customer->id,
            'external_reference' => 'lunar_cust_'.$customer->id,
            'pennylane_customer_id' => 42,
            'payload_snapshot' => $snapshot,
        ]);
    }

    public function test_existing_company_customer_receives_siren_derived_from_siret(): void
    {
        $mapping = $this->mapping(['siret' => '98104397900021'], ['name' => 'Acme']);

        $this->assertTrue($this->mapper()->completeRegNo($mapping));

        $this->http->assertSent(fn ($request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/company_customers/42')
            && $request->data() === ['reg_no' => '981043979']);
        $this->assertSame('981043979', $mapping->fresh()->payload_snapshot['reg_no']);
    }

    public function test_already_complete_or_individual_customers_are_left_untouched(): void
    {
        $complete = $this->mapping(['siret' => '98104397900021'], ['name' => 'Acme', 'reg_no' => '981043979']);
        $individual = $this->mapping(['siret' => '98104397900021'], ['first_name' => 'Jean', 'last_name' => 'Dupont']);
        $withoutSiret = $this->mapping([], ['name' => 'Acme']);

        $this->assertFalse($this->mapper()->completeRegNo($complete));
        $this->assertFalse($this->mapper()->completeRegNo($individual));
        $this->assertFalse($this->mapper()->completeRegNo($withoutSiret));
        $this->http->assertNothingSent();
    }
}

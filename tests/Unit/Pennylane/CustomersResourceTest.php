<?php

declare(strict_types=1);

namespace Tests\Unit\Pennylane;

use Illuminate\Http\Client\Factory as HttpFactory;
use PHPUnit\Framework\TestCase;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CustomersResource;

class CustomersResourceTest extends TestCase
{
    private function resource(HttpFactory $http): CustomersResource
    {
        return new CustomersResource(new PennylaneClient($http, [
            'api_token' => 'test',
            'base_url' => 'https://app.pennylane.com/api/external/v2',
            'http' => ['timeout' => 5, 'retry_times' => 1, 'retry_sleep_ms' => 10],
        ]));
    }

    public function test_company_is_created_on_company_endpoint(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http::response(['id' => 1], 201)]);

        $this->resource($http)->create(['name' => 'Acme'], isCompany: true);

        $http->assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/company_customers'));
    }

    public function test_individual_is_created_on_individual_endpoint(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http::response(['id' => 2], 201)]);

        $this->resource($http)->create(['first_name' => 'Jean', 'last_name' => 'Dupont'], isCompany: false);

        $http->assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/individual_customers'));
    }

    public function test_lookup_filters_on_external_reference(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http::response(['items' => [['id' => 3]], 'has_more' => false], 200)]);

        $found = $this->resource($http)->findByExternalReference('lunar_cust_9');

        $this->assertSame(3, $found['id']);
        $http->assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains(urldecode($request->url()), '"field":"external_reference"'));
    }
}

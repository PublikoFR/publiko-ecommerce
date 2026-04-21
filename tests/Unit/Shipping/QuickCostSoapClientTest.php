<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Pko\ShippingChronopost\Exceptions\QuickCostException;
use Pko\ShippingChronopost\Services\QuickCostSoapClient;
use SoapClient;

class QuickCostSoapClientTest extends TestCase
{
    public function test_missing_credentials_throws(): void
    {
        $client = new QuickCostSoapClient(['account' => '', 'password' => '']);

        $this->expectException(QuickCostException::class);
        $this->expectExceptionMessageMatches('/credentials missing/i');

        $client->quickCost('13', 3.5, '69007', '75001');
    }

    public function test_parses_standard_response(): void
    {
        $soapStub = new class extends SoapClient
        {
            public function __construct() {}

            public function quickCost($args): object
            {
                return (object) [
                    'return' => (object) [
                        'errorCode' => '0',
                        'productCode' => '13',
                        'reservedAmountInclTaxe' => 14.90,
                        'reservedAmountExclTaxe' => 12.42,
                        'currency' => 'EUR',
                    ],
                ];
            }
        };

        $client = new QuickCostSoapClient(
            credentials: ['account' => 'x', 'password' => 'y'],
            client: $soapStub,
        );

        $response = $client->quickCost('13', 3.5, '69007', '75001');

        $this->assertSame('13', $response->serviceCode);
        $this->assertSame(1490, $response->priceCentsTTC);
        $this->assertSame(1242, $response->priceCentsHT);
        $this->assertSame('EUR', $response->currency);
    }

    public function test_api_error_code_throws(): void
    {
        $soapStub = new class extends SoapClient
        {
            public function __construct() {}

            public function quickCost($args): object
            {
                return (object) ['return' => (object) [
                    'errorCode' => '23',
                    'errorMessage' => 'Invalid account',
                ]];
            }
        };

        $client = new QuickCostSoapClient(
            credentials: ['account' => 'x', 'password' => 'y'],
            client: $soapStub,
        );

        $this->expectException(QuickCostException::class);
        $this->expectExceptionMessageMatches('/error \[23\].*Invalid account/');

        $client->quickCost('13', 3.5, '69007', '75001');
    }

    public function test_alternate_amount_field_accepted(): void
    {
        $soapStub = new class extends SoapClient
        {
            public function __construct() {}

            public function quickCost($args): object
            {
                return (object) ['return' => (object) [
                    'errorCode' => '0',
                    'productCode' => '02',
                    'amountTTC' => 18.50,
                ]];
            }
        };

        $client = new QuickCostSoapClient(
            credentials: ['account' => 'x', 'password' => 'y'],
            client: $soapStub,
        );

        $response = $client->quickCost('02', 5.0, '69007', '75001');

        $this->assertSame(1850, $response->priceCentsTTC);
    }
}

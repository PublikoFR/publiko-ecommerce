<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Pko\ShippingColissimo\Services\ColissimoClient;
use Pko\ShippingCommon\Dto\QuoteRequest;

class ColissimoQuoteTest extends TestCase
{
    private function makeClient(): ColissimoClient
    {
        return new ColissimoClient([
            'credentials' => ['contract_number' => 'x', 'password' => 'y'],
            'services' => [
                'DOM' => ['label' => 'Colissimo Domicile', 'enabled' => true],
                'DOS' => ['label' => 'Colissimo Domicile Signature', 'enabled' => true],
            ],
            'grid' => [
                ['max_kg' => 0.5, 'price' => 790],
                ['max_kg' => 2, 'price' => 1090],
                ['max_kg' => 5, 'price' => 1490],
                ['max_kg' => 30, 'price' => 2890],
            ],
            'max_weight_kg' => 30,
        ]);
    }

    public function test_quote_returns_both_services_with_surcharge_for_signature(): void
    {
        $client = $this->makeClient();

        $quotes = $client->quote(new QuoteRequest(
            weightKg: 1.8,
            destinationPostcode: '75001',
            destinationCountry: 'FR',
        ));

        $this->assertCount(2, $quotes);
        $this->assertSame('DOM', $quotes[0]->serviceCode);
        $this->assertSame(1090, $quotes[0]->priceCents);
        $this->assertSame('DOS', $quotes[1]->serviceCode);
        $this->assertSame(1190, $quotes[1]->priceCents);
    }

    public function test_quote_empty_when_over_max_weight(): void
    {
        $client = $this->makeClient();

        $this->assertSame([], $client->quote(new QuoteRequest(40.0, '75001', 'FR')));
    }
}

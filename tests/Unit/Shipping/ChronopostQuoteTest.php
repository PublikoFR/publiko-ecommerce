<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Pko\ShippingChronopost\Services\ChronopostClient;
use Pko\ShippingCommon\Dto\QuoteRequest;

class ChronopostQuoteTest extends TestCase
{
    private function makeClient(): ChronopostClient
    {
        return new ChronopostClient([
            'credentials' => ['account' => 'x', 'password' => 'y', 'sub_account' => ''],
            'services' => [
                '13' => ['label' => 'Chrono 13', 'enabled' => true],
                '16' => ['label' => 'Chrono 18', 'enabled' => false],
                '02' => ['label' => 'Chrono Classic', 'enabled' => true],
            ],
            'grid' => [
                ['max_kg' => 2, 'price' => 1290],
                ['max_kg' => 5, 'price' => 1590],
                ['max_kg' => 10, 'price' => 1990],
                ['max_kg' => 30, 'price' => 3990],
            ],
            'max_weight_kg' => 30,
        ]);
    }

    public function test_quote_returns_all_enabled_services(): void
    {
        $client = $this->makeClient();

        $quotes = $client->quote(new QuoteRequest(
            weightKg: 3.5,
            destinationPostcode: '69007',
            destinationCountry: 'FR',
        ));

        $this->assertCount(2, $quotes);
        $codes = array_map(fn ($q) => $q->serviceCode, $quotes);
        $this->assertSame(['13', '02'], $codes);
        $this->assertSame(1590, $quotes[0]->priceCents);
    }

    public function test_quote_picks_correct_bracket(): void
    {
        $client = $this->makeClient();

        $this->assertSame(1290, $client->quote(new QuoteRequest(1.0, '69007', 'FR'))[0]->priceCents);
        $this->assertSame(1590, $client->quote(new QuoteRequest(5.0, '69007', 'FR'))[0]->priceCents);
        $this->assertSame(1990, $client->quote(new QuoteRequest(7.5, '69007', 'FR'))[0]->priceCents);
        $this->assertSame(3990, $client->quote(new QuoteRequest(25.0, '69007', 'FR'))[0]->priceCents);
    }

    public function test_quote_empty_when_over_max_weight(): void
    {
        $client = $this->makeClient();

        $this->assertSame([], $client->quote(new QuoteRequest(
            weightKg: 40.0,
            destinationPostcode: '69007',
            destinationCountry: 'FR',
        )));
    }
}

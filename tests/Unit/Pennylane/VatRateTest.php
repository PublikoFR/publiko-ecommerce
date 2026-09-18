<?php

declare(strict_types=1);

namespace Tests\Unit\Pennylane;

use PHPUnit\Framework\TestCase;
use Pko\Pennylane\Api\Exceptions\PennylaneException;
use Pko\Pennylane\Support\VatRate;

class VatRateTest extends TestCase
{
    public function test_maps_french_rates_to_pennylane_codes(): void
    {
        $this->assertSame('FR_200', VatRate::toPennylaneCode(20.0));
        $this->assertSame('FR_100', VatRate::toPennylaneCode(10.0));
        $this->assertSame('FR_55', VatRate::toPennylaneCode(5.5));
        $this->assertSame('FR_21', VatRate::toPennylaneCode(2.1));
        $this->assertSame('exempt', VatRate::toPennylaneCode(0.0));
    }

    public function test_rate_derived_from_rounded_amounts_snaps_to_legal_rate(): void
    {
        // 138,24 € HT, 27,65 € de TVA : l'arrondi au centime donne 20,0014 %.
        $rate = VatRate::fromAmounts(13824, 2765);

        $this->assertSame('FR_200', VatRate::toPennylaneCode($rate));
    }

    public function test_no_tax_means_exempt(): void
    {
        $this->assertSame(0.0, VatRate::fromAmounts(1500, 0));
        $this->assertSame(0.0, VatRate::fromAmounts(0, 0));
    }

    public function test_unknown_rate_is_rejected(): void
    {
        $this->expectException(PennylaneException::class);

        VatRate::toPennylaneCode(15.0);
    }
}

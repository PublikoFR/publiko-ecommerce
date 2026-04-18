<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Pko\ShippingCommon\Support\ZoneResolver;

class ZoneResolverTest extends TestCase
{
    public function test_metropolitan_postcodes_are_accepted(): void
    {
        $this->assertTrue(ZoneResolver::isMetropole('69007'));
        $this->assertTrue(ZoneResolver::isMetropole('75001'));
        $this->assertTrue(ZoneResolver::isMetropole('59000', 'FR'));
    }

    public function test_corsica_postcodes_are_rejected(): void
    {
        $this->assertFalse(ZoneResolver::isMetropole('20000'));
        $this->assertFalse(ZoneResolver::isMetropole('20200'));
    }

    public function test_dom_postcodes_are_rejected(): void
    {
        $this->assertFalse(ZoneResolver::isMetropole('97100'));
        $this->assertFalse(ZoneResolver::isMetropole('97400'));
        $this->assertFalse(ZoneResolver::isMetropole('97800'));
    }

    public function test_non_fr_country_is_rejected(): void
    {
        $this->assertFalse(ZoneResolver::isMetropole('10115', 'DE'));
        $this->assertFalse(ZoneResolver::isMetropole('1000', 'BE'));
    }

    public function test_invalid_postcode_is_rejected(): void
    {
        $this->assertFalse(ZoneResolver::isMetropole(''));
        $this->assertFalse(ZoneResolver::isMetropole('ABC'));
        $this->assertFalse(ZoneResolver::isMetropole('1234'));
        $this->assertFalse(ZoneResolver::isMetropole('123456'));
    }

    public function test_postcode_with_whitespace_is_normalized(): void
    {
        $this->assertTrue(ZoneResolver::isMetropole(' 75001 '));
    }
}

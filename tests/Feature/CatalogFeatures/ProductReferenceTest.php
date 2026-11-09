<?php

declare(strict_types=1);

namespace Tests\Feature\CatalogFeatures;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Brand;
use Lunar\Models\Language;
use Pko\CatalogFeatures\Support\BrandReferencePrefix;
use Pko\CatalogFeatures\Support\ProductReference;
use Tests\TestCase;

/**
 * Référence boutique = préfixe marque + référence fabricant (MPN brut).
 */
class ProductReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Brand/Product génèrent une URL : langue par défaut requise.
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);
    }

    public function test_default_prefix_is_three_first_letters_without_accents(): void
    {
        $this->assertSame('SOM', BrandReferencePrefix::suggest('Somfy'));
        $this->assertSame('ELE', BrandReferencePrefix::suggest('Électro Pro'));
        $this->assertSame('CAM', BrandReferencePrefix::suggest('  came-2 '));
        $this->assertSame('LG', BrandReferencePrefix::suggest('LG'));
        $this->assertNull(BrandReferencePrefix::suggest('3M'));
        $this->assertNull(BrandReferencePrefix::suggest(''));
    }

    public function test_prefix_format_is_2_to_5_uppercase_alphanumerics(): void
    {
        $this->assertTrue(BrandReferencePrefix::isValid('SO'));
        $this->assertTrue(BrandReferencePrefix::isValid('SOM12'));
        $this->assertFalse(BrandReferencePrefix::isValid('S'));
        $this->assertFalse(BrandReferencePrefix::isValid('SOMFYX'));
        $this->assertFalse(BrandReferencePrefix::isValid('som'));
        $this->assertFalse(BrandReferencePrefix::isValid('SO-M'));
        $this->assertSame('SOM', BrandReferencePrefix::normalize(' som '));
    }

    public function test_collision_is_detected_and_alternatives_are_free(): void
    {
        $somfy = Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);
        Brand::create(['name' => 'Autre', 'pko_ref_prefix' => 'SOMA']);

        $this->assertTrue(BrandReferencePrefix::isTaken('SOM'));
        $this->assertFalse(BrandReferencePrefix::isTaken('SOM', (int) $somfy->id));
        $this->assertNull(BrandReferencePrefix::freeSuggestion('Somatic'));

        $alternatives = BrandReferencePrefix::alternatives('Somatic');
        $this->assertCount(3, $alternatives);
        $this->assertNotContains('SOM', $alternatives);
        $this->assertNotContains('SOMA', $alternatives);
        foreach ($alternatives as $alt) {
            $this->assertTrue(BrandReferencePrefix::isValid($alt));
        }

        $message = BrandReferencePrefix::collisionMessage('SOM', $somfy, 'Somatic');
        $this->assertStringContainsString('Somfy', $message);
        $this->assertStringContainsString($alternatives[0], $message);
    }

    public function test_prefix_is_unique_in_database(): void
    {
        Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);

        $this->expectException(QueryException::class);
        Brand::create(['name' => 'Somatic', 'pko_ref_prefix' => 'SOM']);
    }

    public function test_compose_keeps_mpn_exactly(): void
    {
        $brand = Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);

        $this->assertSame('SOM1240385', ProductReference::compose($brand, '1240385'));
        $this->assertSame('SOM001240385', ProductReference::compose($brand, '001240385'));
        $this->assertSame('SOM12-40/38.5 b', ProductReference::compose($brand, '12-40/38.5 b'));
        $this->assertSame('SOM42', ProductReference::compose($brand, 42));
    }

    public function test_compose_returns_null_without_prefix_or_mpn(): void
    {
        $brand = Brand::create(['name' => 'Somfy', 'pko_ref_prefix' => 'SOM']);
        $noPrefix = Brand::create(['name' => 'Sans']);

        $this->assertNull(ProductReference::compose($noPrefix, '1240385'));
        $this->assertNull(ProductReference::compose(null, '1240385'));
        $this->assertNull(ProductReference::compose($brand, null));
        $this->assertNull(ProductReference::compose($brand, ''));
        $this->assertNull(ProductReference::compose($brand, '   '));
    }
}

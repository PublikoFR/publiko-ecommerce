<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\AiImporter\Services\FeatureCatalog;
use Pko\CatalogFeatures\Models\FeatureFamily;
use Pko\CatalogFeatures\Models\FeatureValue;
use Tests\TestCase;

class FeatureCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureCatalog::flush();

        $family = FeatureFamily::create(['handle' => 'couple-nm', 'name' => 'Couple (Nm)']);
        FeatureValue::create(['feature_family_id' => $family->id, 'handle' => '10-nm', 'name' => '10 Nm']);
        FeatureValue::create(['feature_family_id' => $family->id, 'handle' => '6-nm', 'name' => '6 Nm']);
        $conn = FeatureFamily::create(['handle' => 'connectivite', 'name' => 'Connectivité']);
        FeatureValue::create(['feature_family_id' => $conn->id, 'handle' => 'wifi', 'name' => 'WiFi']);
    }

    public function test_expand_lists_live_families_with_their_values(): void
    {
        $out = (new FeatureCatalog)->expand("Caractéristiques :\n{{features}}");

        $this->assertStringContainsString('Connectivité : WiFi', $out);
        $this->assertStringContainsString('Couple (Nm) : 10 Nm, 6 Nm', $out);
        $this->assertStringNotContainsString('{{features}}', $out);
    }

    public function test_context_without_placeholder_is_left_untouched(): void
    {
        $this->assertSame('Ton sobre.', (new FeatureCatalog)->expand('Ton sobre.'));
    }

    public function test_parse_maps_ai_output_names_to_handles_ignoring_case_and_accents(): void
    {
        $parsed = (new FeatureCatalog)->parse('connectivite:wifi|Couple (Nm):6 nm,10 Nm|Marque:SOMFY|Connectivité:Zigbee');

        $this->assertSame(['connectivite' => ['wifi'], 'couple-nm' => ['6-nm', '10-nm']], $parsed['features']);
        $this->assertSame(['family:Marque', 'value:Connectivité.Zigbee'], $parsed['unresolved']);
    }

    public function test_catalog_is_reread_after_flush(): void
    {
        $catalog = new FeatureCatalog;
        $this->assertCount(2, $catalog->lines());

        FeatureFamily::create(['handle' => 'gamme', 'name' => 'Gamme']);
        $this->assertCount(2, $catalog->lines(), 'mémoïsé le temps du job');

        FeatureCatalog::flush();
        $this->assertCount(3, $catalog->lines());
    }
}

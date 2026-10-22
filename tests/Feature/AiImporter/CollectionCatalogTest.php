<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Language;
use Pko\AiImporter\Services\CollectionCatalog;
use Tests\TestCase;

class CollectionCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $name, ?Collection $parent = null): Collection
    {
        $group = CollectionGroup::firstOrCreate(['handle' => 'catalogue'], ['name' => 'Catalogue']);
        $c = Collection::create([
            'collection_group_id' => $group->id,
            'attribute_data' => collect(['name' => new TranslatedText(collect(['fr' => $name]))]),
        ]);
        if ($parent !== null) {
            $c->appendToNode($parent)->save();
        }

        return $c;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);
        CollectionCatalog::flush();
    }

    public function test_expand_replaces_placeholder_with_live_category_paths(): void
    {
        $moteurs = $this->make('Motorisations');
        $this->make('Moteurs de volets', $moteurs);

        $out = (new CollectionCatalog)->expand("Catégories :\n{{categories}}\nFin.");

        $this->assertStringContainsString("Accueil>Motorisations\nAccueil>Motorisations>Moteurs de volets", $out);
        $this->assertStringNotContainsString('{{categories}}', $out);
    }

    public function test_context_without_placeholder_is_left_untouched(): void
    {
        $this->make('Motorisations');

        $this->assertSame('Ton sobre.', (new CollectionCatalog)->expand('Ton sobre.'));
    }

    public function test_find_id_by_path_walks_names_ignoring_case_accents_and_root(): void
    {
        $a = $this->make('Motorisations');
        $b = $this->make('Éléments', $a);

        $catalog = new CollectionCatalog;

        $this->assertSame($b->id, $catalog->findIdByPath('Accueil>motorisations>ELEMENTS'));
        $this->assertSame($a->id, $catalog->findIdByPath('Motorisations'));
        $this->assertNull($catalog->findIdByPath('Accueil>Motorisations>Inconnue'));
        $this->assertNull($catalog->findIdByPath('Accueil'));
    }

    public function test_catalog_is_reread_after_flush(): void
    {
        $catalog = new CollectionCatalog;
        $this->assertSame([], $catalog->paths());

        $this->make('Nouvelle');
        $this->assertSame([], $catalog->paths(), 'mémoïsé le temps du job');

        CollectionCatalog::flush();
        $this->assertSame(['Accueil>Nouvelle'], $catalog->paths());
    }
}

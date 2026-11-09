<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Lunar\FieldTypes\Text as LunarText;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Language;
use Lunar\Models\Url;
use Tests\TestCase;

/**
 * Pictos du menu latéral : import par nom de fichier (ids de catégories
 * différents d'un environnement à l'autre) et rendu SVG dans le menu.
 */
class CategoryIconsImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        Storage::fake(config('media-library.disk_name'));
        // Les racines du seeder ne doivent pas interférer avec le rapprochement par nom.
        LunarCollection::query()->whereNull('parent_id')->update(['pko_enabled' => false]);

        $this->dir = sys_get_temp_dir().'/pko-icons-'.uniqid();
        mkdir($this->dir);
        foreach (['portails', 'portes-garage-fermetures-industrielles', 'inconnu'] as $name) {
            file_put_contents("{$this->dir}/{$name}.svg", '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect width="48" height="48" fill="#aac932"/></svg>');
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $gates = $this->makeRoot('Portails', 'portails-test');

        $this->artisan('storefront:import-category-icons', ['dir' => $this->dir, '--dry-run' => true])
            ->expectsOutputToContain('à importer');

        $this->assertCount(0, $gates->fresh()->getMedia('images'));
    }

    public function test_import_matches_by_name_puts_icon_first_and_is_idempotent(): void
    {
        $gates = $this->makeRoot('Portails', 'portails-test');
        $garage = $this->makeRoot('Portes de garage & fermetures industrielles', 'garage-test');
        // Racine désactivée homonyme : la catégorie active gagne.
        $this->makeRoot('Portails', 'portails-off')->forceFill(['pko_enabled' => false])->save();

        // Une photo existante reste, mais passe après le picto.
        $photo = sys_get_temp_dir().'/pko-photo-'.uniqid().'.svg';
        file_put_contents($photo, '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $gates->addMedia($photo)->toMediaCollection('images');

        // Un fichier sans catégorie fait échouer la commande (code retour ≠ 0).
        $this->artisan('storefront:import-category-icons', ['dir' => $this->dir])->assertFailed();
        $this->artisan('storefront:import-category-icons', ['dir' => $this->dir])->assertFailed();

        $images = $gates->fresh()->getMedia('images');
        $this->assertCount(2, $images, 'Ré-import : le picto du même nom est remplacé, pas empilé.');
        $this->assertSame('portails.svg', $gates->fresh()->getFirstMedia('images')->file_name);
        $this->assertSame('portes-garage-fermetures-industrielles.svg', $garage->fresh()->getFirstMedia('images')->file_name);
    }

    public function test_lateral_menu_serves_the_original_svg(): void
    {
        $gates = $this->makeRoot('Portails', 'portails-test');
        $this->artisan('storefront:import-category-icons', ['dir' => $this->dir]);

        $url = $gates->fresh()->getFirstMedia('images')->getUrl();

        $this->get('/')->assertOk()->assertSee($url, false)->assertSee('group-hover:bg-accent-500', false);
    }

    public function test_svg_is_served_as_original_even_when_a_raster_conversion_exists(): void
    {
        $gates = $this->makeRoot('Portails', 'portails-test');
        $this->artisan('storefront:import-category-icons', ['dir' => $this->dir]);

        // Sur un serveur avec Imagick, Spatie génère des JPG à partir du SVG.
        $media = $gates->fresh()->getFirstMedia('images');
        $media->markAsConversionGenerated('small');
        $media->save();

        $this->assertTrue($media->fresh()->hasGeneratedConversion('small'));
        $this->assertSame($media->getUrl(), pko_media_url($media->fresh(), 'small'));
        $this->get('/')->assertOk()->assertSee($media->getUrl(), false)->assertDontSee('portails-small', false);
    }

    private function makeRoot(string $name, string $slug): LunarCollection
    {
        $collection = new LunarCollection([
            'collection_group_id' => LunarCollection::query()->value('collection_group_id') ?? CollectionGroup::query()->value('id'),
            'type' => 'static',
            'sort' => 'custom',
            'attribute_data' => collect(['name' => new TranslatedText(collect(['fr' => new LunarText($name)]))]),
        ]);
        $collection->saveAsRoot();

        $collection->urls()->delete();
        Url::create([
            'language_id' => Language::query()->value('id'),
            'element_type' => $collection->getMorphClass(),
            'element_id' => $collection->id,
            'slug' => $slug,
            'default' => true,
        ]);

        return $collection->refresh();
    }
}

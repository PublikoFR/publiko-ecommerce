<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages\EditImporterConfig;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages\ListImporterConfigs;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Support\PipelineSummary;
use Tests\TestCase;

/**
 * Smoke test of the blueprint mapping editor : the Edit page must render the
 * dense table for a real PrestaShop-flavoured config (friendly labels, sheet
 * badges, the « masquer colonnes vides » toolbar) without throwing — covering
 * the `View` toolbar component, the repeater build and {@see PipelineSummary}
 * on every row.
 */
class EditImporterConfigTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'staff@example.test',
            'password' => 'secret123',
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');
    }

    private function config(): ImporterConfig
    {
        return ImporterConfig::create([
            'name' => 'SOMFY-TEST',
            'supplier_name' => 'SOMFY',
            'config_data' => [
                'type' => 'FAB-DIS',
                'primary_sheet' => 'B01_COMMERCE',
                'mapping' => [
                    'price_tex' => [
                        'col' => 'M',
                        'sheet' => 'B01_COMMERCE',
                        'default' => '0',
                        'actions' => [
                            [
                                'type' => 'condition',
                                'branches' => [[
                                    'logic' => 'AND',
                                    'rules' => [['field' => 'AB', 'operator' => '=', 'value' => 'GTK']],
                                    'actions' => [['type' => 'multiply', 'value' => 1.2]],
                                ]],
                                'else_actions' => [['type' => 'multiply', 'value' => 1.2]],
                            ],
                            ['type' => 'round', 'decimals' => 2],
                        ],
                    ],
                    'tags' => [
                        'col' => 'I',
                        'sheet' => 'B01_COMMERCE',
                        'actions' => [['type' => 'concat', 'sources' => [['col' => 'A'], ['col' => 'B']]]],
                    ],
                    'mpn' => [],
                ],
            ],
        ]);
    }

    public function test_edit_page_renders_blueprint_without_error(): void
    {
        $this->actAsAdmin();
        $config = $this->config();

        Livewire::test(EditImporterConfig::class, ['record' => $config->getRouteKey()])
            ->assertOk()
            ->assertSee('Prix HT')                    // libellé ami pour price_tex
            ->assertSee('Masquer les colonnes vides')  // toolbar blueprint
            ->assertSee('B01_COMMERCE')                // badge feuille du résumé
            ->assertSee('ALORS');                      // condition rendue dans le résumé
    }

    public function test_saving_the_editor_never_disables_sheet_headers(): void
    {
        $this->actAsAdmin();

        // Config façon PrestaShop : `skip_first_row` absent (= en-têtes), jointure
        // et colonnes désignées par leur intitulé.
        $config = ImporterConfig::create([
            'name' => 'FABDIS-TEST',
            'config_data' => [
                'primary_sheet' => 'B01_COMMERCE',
                'join_key' => 'REFCIALE',
                'sheets' => [
                    'B01_COMMERCE' => ['relation' => 'one', 'join_col' => 'B'],
                    'B03_MEDIA' => ['relation' => 'many', 'join_col' => 'B', 'type_col' => 'MTYP'],
                ],
                'mapping' => [
                    'reference' => ['col' => 'REFCIALE', 'sheet' => 'B01_COMMERCE', 'prefix' => 'SOM'],
                    'name' => ['col' => 'H', 'sheet' => 'B01_COMMERCE'],
                    'supplier' => ['default' => 'SOMFY'],
                ],
            ],
        ]);
        $before = $config->config_data->getArrayCopy();

        Livewire::test(EditImporterConfig::class, ['record' => $config->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        // Avant le correctif : `skip_first_row: false` était écrit sur chaque feuille.
        // (assertEquals : MySQL réordonne les clés d'un objet JSON.)
        $after = $config->refresh()->config_data->getArrayCopy();
        unset($after['mapping_order']);
        $this->assertEquals($before, $after);
    }

    public function test_editor_warns_about_a_config_that_cannot_work(): void
    {
        $this->actAsAdmin();

        $config = ImporterConfig::create([
            'name' => 'CASSEE',
            'config_data' => [
                'primary_sheet' => 'B01_COMMERCE',
                'join_key' => 'REFCIALE',
                'sheets' => ['B01_COMMERCE' => ['skip_first_row' => false]],
                'mapping' => ['reference' => ['col' => 'REFCIALE']],
            ],
        ]);

        Livewire::test(EditImporterConfig::class, ['record' => $config->getRouteKey()])
            ->assertOk()
            ->assertSee('1ʳᵉ ligne = en-têtes » est décoché');
    }

    public function test_mapping_rows_read_as_one_line(): void
    {
        $label = (string) ImporterConfigResource::mappingItemLabel([
            'key' => 'price_tex', 'sheet' => 'B01_COMMERCE', 'col' => 'M', 'default' => '0',
            'actions' => [['type' => 'multiply', 'value' => 1.2], ['type' => 'round', 'decimals' => 2]],
        ]);

        $this->assertStringContainsString('Prix HT', $label);
        $this->assertStringContainsString('B01_COMMERCE › M · défaut 0 · Multiplier ×1.2 → Arrondir (2)', $label);

        // Colonne PrestaShop sans champ catalogue, sans source.
        $ignored = (string) ImporterConfigResource::mappingItemLabel(['key' => 'unity']);
        $this->assertStringContainsString('non importée', $ignored);
        $this->assertStringContainsString('aucune configuration', $ignored);

        $ai = (string) ImporterConfigResource::mappingItemLabel([
            'key' => 'description', 'actions' => [['type' => 'llm_transform', 'prompt' => 'x']],
        ]);
        $this->assertStringContainsString('>IA<', $ai);
    }

    public function test_configurations_list_offers_the_next_step(): void
    {
        $this->actAsAdmin();
        $config = $this->config();

        Livewire::test(ListImporterConfigs::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$config])
            ->assertTableActionExists('newImport')
            ->assertActionExists('importJson')
            // Aucune colonne d'identification dans cette config : signalée dès la liste.
            ->assertSee('À corriger');
    }
}

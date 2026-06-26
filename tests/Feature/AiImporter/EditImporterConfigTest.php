<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages\EditImporterConfig;
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
}

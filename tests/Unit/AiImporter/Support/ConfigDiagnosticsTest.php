<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter\Support;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
use Pko\AiImporter\Support\ConfigDiagnostics;

class ConfigDiagnosticsTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fabDis(): array
    {
        return [
            'primary_sheet' => 'B01_COMMERCE',
            'join_key' => 'REFCIALE',
            'sheets' => [
                'B01_COMMERCE' => ['relation' => 'one', 'join_col' => 'B'],
                'B03_MEDIA' => ['relation' => 'many', 'join_col' => 'B', 'type_col' => 'MTYP'],
            ],
            'mapping' => [
                'reference' => ['col' => 'REFCIALE', 'sheet' => 'B01_COMMERCE'],
                'name' => ['col' => 'H', 'sheet' => 'B01_COMMERCE'],
                'price_tex' => ['col' => 'M', 'sheet' => 'B01_COMMERCE'],
                'supplier' => ['default' => 'SOMFY'],
                'unity' => ['default' => ''],
            ],
        ];
    }

    /** @param  array<int, array{level: string, message: string}>  $findings */
    private function levels(array $findings): array
    {
        return array_column($findings, 'level');
    }

    public function test_healthy_config_has_no_error(): void
    {
        $findings = ConfigDiagnostics::analyze($this->fabDis());

        $this->assertFalse(ConfigDiagnostics::hasErrors($findings));
        // La clé PrestaShop sans champ catalogue est signalée pour information.
        $this->assertStringContainsString('unity', implode(' ', array_column($findings, 'message')));
    }

    public function test_empty_mapping_is_an_error(): void
    {
        $this->assertTrue(ConfigDiagnostics::hasErrors(ConfigDiagnostics::analyze(['mapping' => []])));
    }

    public function test_missing_identity_column_is_an_error(): void
    {
        $config = $this->fabDis();
        unset($config['mapping']['reference']);

        $this->assertContains(ConfigDiagnostics::ERROR, $this->levels(ConfigDiagnostics::analyze($config)));
    }

    public function test_brand_and_mpn_are_a_valid_identity(): void
    {
        $config = $this->fabDis();
        unset($config['mapping']['reference']);
        $config['mapping']['manufacturer'] = ['col' => 'A'];
        $config['mapping']['mpn'] = ['col' => 'B'];

        $this->assertFalse(ConfigDiagnostics::hasErrors(ConfigDiagnostics::analyze($config)));
    }

    public function test_headers_disabled_with_named_columns_is_an_error(): void
    {
        $config = $this->fabDis();
        $config['sheets']['B01_COMMERCE']['skip_first_row'] = false;

        $findings = ConfigDiagnostics::analyze($config);

        $this->assertTrue(ConfigDiagnostics::hasErrors($findings));
        $this->assertStringContainsString('REFCIALE', implode(' ', array_column($findings, 'message')));
    }

    public function test_undeclared_sheet_is_an_error(): void
    {
        $config = $this->fabDis();
        $config['mapping']['weight'] = ['col' => 'P', 'sheet' => 'B02_LOGISTIQUE'];

        $messages = implode(' ', array_column(ConfigDiagnostics::analyze($config), 'message'));

        $this->assertStringContainsString('B02_LOGISTIQUE', $messages);
    }

    public function test_repair_restores_headers_when_all_sheets_lost_them(): void
    {
        $config = $this->fabDis();
        foreach (array_keys($config['sheets']) as $sheet) {
            $config['sheets'][$sheet]['skip_first_row'] = false;
        }

        [$repaired, $changed] = ConfigDiagnostics::repairHeaderFlags($config);

        $this->assertTrue($changed);
        $this->assertArrayNotHasKey('skip_first_row', $repaired['sheets']['B01_COMMERCE']);
        $this->assertArrayNotHasKey('skip_first_row', $repaired['sheets']['B03_MEDIA']);
        $this->assertFalse(ConfigDiagnostics::hasErrors(ConfigDiagnostics::analyze($repaired)));
    }

    public function test_repair_leaves_a_deliberate_headerless_sheet_alone(): void
    {
        // Une seule feuille sans en-têtes, les autres avec : choix délibéré.
        $config = $this->fabDis();
        $config['sheets']['B03_MEDIA']['skip_first_row'] = false;
        $this->assertFalse(ConfigDiagnostics::repairHeaderFlags($config)[1]);

        // Toutes sans en-têtes mais colonnes désignées par lettres : cohérent.
        $letters = [
            'primary_sheet' => 'S1',
            'join_key' => 'A',
            'sheets' => ['S1' => ['skip_first_row' => false], 'S2' => ['skip_first_row' => false, 'join_col' => 'B']],
            'mapping' => ['reference' => ['col' => 'A']],
        ];
        $this->assertFalse(ConfigDiagnostics::repairHeaderFlags($letters)[1]);
    }

    public function test_visual_editor_round_trip_never_disables_headers(): void
    {
        $config = $this->fabDis();

        // Le formulaire ré-émet l'état hydraté : avant le correctif, la clé
        // absente devenait `false` au premier enregistrement.
        $hydrated = ImporterConfigResource::hydrateVisual(['config_data' => $config]);
        foreach ($hydrated['config_data']['sheets_repeater'] as $sheet) {
            $this->assertTrue($sheet['skip_first_row']);
        }

        $saved = ImporterConfigResource::dehydrateVisual($hydrated)['config_data'];
        $this->assertSame($config['sheets'], $saved['sheets']);
    }

    public function test_explicitly_disabled_headers_survive_the_round_trip(): void
    {
        $config = $this->fabDis();
        $config['sheets']['B03_MEDIA']['skip_first_row'] = false;

        $saved = ImporterConfigResource::dehydrateVisual(
            ImporterConfigResource::hydrateVisual(['config_data' => $config]),
        )['config_data'];

        $this->assertFalse($saved['sheets']['B03_MEDIA']['skip_first_row']);
    }
}

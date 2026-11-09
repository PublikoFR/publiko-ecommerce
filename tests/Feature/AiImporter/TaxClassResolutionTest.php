<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarProductWriter;
use Tests\TestCase;

/**
 * Résolution de la classe de taxe à l'import : toujours la classe par défaut,
 * jamais la « première » (FEAT-003 — variantes vendues sans TVA).
 */
class TaxClassResolutionTest extends TestCase
{
    use RefreshDatabase;

    private TaxClass $orphan;

    private TaxClass $standard;

    protected function setUp(): void
    {
        parent::setUp();
        ProductType::firstOrCreate(['name' => 'Test Type']);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'exchange_rate' => 1.0, 'default' => true, 'enabled' => true]);
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);

        // Reproduit l'état de prod : la classe d'id le plus bas n'est PAS la
        // classe par défaut (« Default Tax Class » de lunar:install, sans taux).
        $this->orphan = TaxClass::create(['name' => 'Default Tax Class', 'default' => false]);
        $this->standard = TaxClass::create(['name' => 'TVA 20%', 'default' => true]);
    }

    public function test_missing_handle_falls_back_on_default_class_not_first(): void
    {
        $record = $this->record('SKU-TAX-1', []);

        (new LunarProductWriter)->write($record);

        $this->assertSame(StagingStatus::Created, $record->refresh()->status);
        $this->assertSame($this->standard->id, ProductVariant::where('sku', 'SKU-TAX-1')->value('tax_class_id'));
    }

    public function test_known_name_is_matched_case_insensitively(): void
    {
        $record = $this->record('SKU-TAX-2', ['tax_class_handle' => 'default tax class']);

        (new LunarProductWriter)->write($record);

        $this->assertSame($this->orphan->id, ProductVariant::where('sku', 'SKU-TAX-2')->value('tax_class_id'));
        $this->assertFalse(ImportLog::where('import_job_id', $record->import_job_id)->where('level', 'warning')->exists());
    }

    public function test_unknown_handle_falls_back_on_default_with_warning(): void
    {
        $record = $this->record('SKU-TAX-3', ['tax_class_handle' => 'standard']);

        (new LunarProductWriter)->write($record);

        $this->assertSame($this->standard->id, ProductVariant::where('sku', 'SKU-TAX-3')->value('tax_class_id'));
        $this->assertTrue(
            ImportLog::where('import_job_id', $record->import_job_id)
                ->where('level', 'warning')
                ->where('message', 'like', '%Classe de taxe « standard » introuvable%')
                ->exists(),
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function record(string $sku, array $extra): StagingRecord
    {
        $job = ImportJob::create([
            'input_file_path' => 'n/a',
            'status' => 'pending',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
        ]);

        return StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => 2,
            'data' => ['reference' => $sku, 'name' => 'Produit '.$sku, 'price_cents' => 1000] + $extra,
            'status' => StagingStatus::Pending,
        ]);
    }
}

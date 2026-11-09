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
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\AiImporter\Support\ProductFieldCatalog;
use Tests\TestCase;

/**
 * Éco-participation à l'import (modèle « dont », cf. docs/ecotax.md).
 */
class WriterEcotaxTest extends TestCase
{
    use RefreshDatabase;

    private ImportJob $job;

    private int $row = 1;

    protected function setUp(): void
    {
        parent::setUp();
        ProductType::firstOrCreate(['name' => 'Test Type']);
        TaxClass::firstOrCreate(['name' => 'Standard'], ['default' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'exchange_rate' => 1.0, 'default' => true, 'enabled' => true]);
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);

        $this->job = ImportJob::create([
            'input_file_path' => 'n/a', 'status' => 'pending', 'import_status' => 'pending', 'error_policy' => 'ignore',
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function write(array $data, array $options = []): StagingRecord
    {
        $record = StagingRecord::create([
            'import_job_id' => $this->job->id,
            'row_number' => $this->row++,
            'data' => $data,
            'status' => StagingStatus::Pending,
        ]);

        (new LunarProductWriter)->configure($options)->write($record);

        return $record->refresh();
    }

    private function variant(string $sku): ProductVariant
    {
        return ProductVariant::where('sku', $sku)->firstOrFail();
    }

    private function priceOf(string $sku): int
    {
        return (int) $this->variant($sku)->prices()->first()->price->value;
    }

    public function test_price_tex_alias_adds_ecotax_to_price(): void
    {
        $this->write(['reference' => 'ECO-1', 'name' => 'Moteur', 'price_tex' => '186,52', 'ecotax' => '0,16']);

        $this->assertSame(16, (int) $this->variant('ECO-1')->pko_ecotax_cents);
        $this->assertSame(18668, $this->priceOf('ECO-1'));
    }

    public function test_direct_price_cents_is_considered_ecotax_inclusive(): void
    {
        $this->write(['reference' => 'ECO-2', 'name' => 'Moteur', 'price_cents' => 18668, 'ecotax_cents' => 16]);

        $this->assertSame(16, (int) $this->variant('ECO-2')->pko_ecotax_cents);
        $this->assertSame(18668, $this->priceOf('ECO-2'));
    }

    public function test_blank_ecotax_is_ignored_never_zero(): void
    {
        $this->write(['reference' => 'ECO-3', 'name' => 'Moteur', 'price_tex' => '10', 'ecotax' => '']);

        $this->assertNull($this->variant('ECO-3')->pko_ecotax_cents);
        $this->assertSame(1000, $this->priceOf('ECO-3'));

        // Une MAJ avec éco-taxe vide ne remet pas à zéro une valeur existante.
        $this->write(['reference' => 'ECO-3', 'price_tex' => '10', 'ecotax' => '0,19']);
        $this->write(['reference' => 'ECO-3', 'price_tex' => '10', 'ecotax' => '  ']);
        $this->assertSame(19, (int) $this->variant('ECO-3')->pko_ecotax_cents);
        $this->assertSame(1000, $this->priceOf('ECO-3'));
    }

    public function test_update_mode_price_writes_price_and_ecotax_together(): void
    {
        $this->write(['reference' => 'ECO-4', 'name' => 'Moteur', 'price_tex' => '100', 'stock' => 5]);
        $this->assertNull($this->variant('ECO-4')->pko_ecotax_cents);

        $this->write(
            ['reference' => 'ECO-4', 'price_tex' => '120', 'ecotax' => '0.18', 'stock' => 99],
            ['update_mode' => 'price'],
        );

        $variant = $this->variant('ECO-4');
        $this->assertSame(18, (int) $variant->pko_ecotax_cents);
        $this->assertSame(12018, $this->priceOf('ECO-4'));
        $this->assertSame(5, (int) $variant->stock);
    }

    public function test_update_mode_all_writes_ecotax(): void
    {
        $this->write(['reference' => 'ECO-5', 'name' => 'Moteur', 'price_tex' => '50']);
        $this->write(['reference' => 'ECO-5', 'price_tex' => '50', 'ecotax' => 0.17], ['update_mode' => 'all']);

        $this->assertSame(17, (int) $this->variant('ECO-5')->pko_ecotax_cents);
        $this->assertSame(5017, $this->priceOf('ECO-5'));
    }

    public function test_normalization_contract(): void
    {
        $this->assertSame(
            ['price_cents' => 18668, 'ecotax_cents' => 16],
            LunarProductWriter::normalizeLegacyKeys(['price_tex' => '186.52', 'ecotax' => '0.16']),
        );
        // Arrondi sur la somme des euros : round((10,004 + 0,004) × 100) = 1001,
        // et non 1000 + 0 en additionnant deux montants déjà arrondis.
        $this->assertSame(
            ['price_cents' => 1001, 'ecotax_cents' => 0],
            LunarProductWriter::normalizeLegacyKeys(['price_tex' => '10,004', 'ecotax' => '0,004']),
        );
        // Éco-taxe déjà en cents canoniques : addition en cents.
        $this->assertSame(
            ['ecotax_cents' => 16, 'price_cents' => 18668],
            LunarProductWriter::normalizeLegacyKeys(['price_tex' => '186.52', 'ecotax_cents' => 16]),
        );
        // price_cents canonique : l'éco-taxe est réputée incluse, rien n'est ajouté.
        $this->assertSame(
            ['price_cents' => 1000, 'ecotax_cents' => 16],
            LunarProductWriter::normalizeLegacyKeys(['price_cents' => 1000, 'price_tex' => '9', 'ecotax' => '0.16']),
        );
        $warnings = [];
        $this->assertSame(
            ['price_cents' => 1000],
            LunarProductWriter::normalizeLegacyKeys(['price_tex' => '10', 'ecotax' => 'N/C'], $warnings),
        );
        $this->assertSame(['ecotax' => 'N/C'], $warnings);
    }

    public function test_ecotax_is_a_selectable_canonical_key(): void
    {
        $this->assertContains('ecotax_cents', LunarProductWriter::CANONICAL_KEYS);
        $this->assertSame('ecotax_cents', LunarProductWriter::LEGACY_EURO_ALIASES['ecotax']);
        $this->assertArrayHasKey('ecotax_cents', ProductFieldCatalog::flat());
        $this->assertSame('Éco-participation', ProductFieldCatalog::label('ecotax'));
    }
}

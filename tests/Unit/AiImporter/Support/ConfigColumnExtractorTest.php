<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter\Support;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Support\ConfigColumnExtractor;

/**
 * Couvre l'extraction « Colonnes à traiter » de la page Préparer un fichier :
 * détection IA (llm_transform), exclusion des colonnes purement « default »,
 * libellés avec source, et les helpers de pré-cochage / désélection IA.
 */
class ConfigColumnExtractorTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function mapping(): array
    {
        return [
            'reference' => ['col' => 'A', 'sheet' => 'B01'],
            'name' => ['col' => 'C', 'actions' => [['type' => 'trim']]],
            'description' => ['col' => 'D', 'actions' => [['type' => 'llm_transform', 'prompt' => 'x']]],
            'tax_class_handle' => ['default' => 'standard'], // statique → exclue
            'legacy_ai' => ['col' => 'E', 'action' => ['type' => 'llm_transform']], // action singulier v0
            'empty' => [], // ni col ni action → exclue
        ];
    }

    public function test_extracts_processable_columns_only(): void
    {
        $keys = array_column(ConfigColumnExtractor::fromMapping($this->mapping()), 'value');

        sort($keys);
        $this->assertSame(['description', 'legacy_ai', 'name', 'reference'], $keys);
    }

    public function test_detects_ai_columns(): void
    {
        $byKey = [];
        foreach (ConfigColumnExtractor::fromMapping($this->mapping()) as $col) {
            $byKey[$col['value']] = $col['has_ai'];
        }

        $this->assertTrue($byKey['description']);
        $this->assertTrue($byKey['legacy_ai']);
        $this->assertFalse($byKey['name']);
        $this->assertFalse($byKey['reference']);
    }

    public function test_label_includes_sheet_and_column_source(): void
    {
        $labels = [];
        foreach (ConfigColumnExtractor::fromMapping($this->mapping()) as $col) {
            $labels[$col['value']] = $col['label'];
        }

        $this->assertSame('reference (B01:A)', $labels['reference']);
        $this->assertSame('legacy_ai (colonne E)', $labels['legacy_ai']);
    }

    public function test_sorts_alphabetically_by_label(): void
    {
        $labels = array_column(ConfigColumnExtractor::fromMapping($this->mapping()), 'label');
        $sorted = $labels;
        sort($sorted);

        $this->assertSame($sorted, $labels);
    }

    public function test_null_config_yields_empty(): void
    {
        $this->assertSame([], ConfigColumnExtractor::fromConfig(null));
        $this->assertSame([], ConfigColumnExtractor::aiColumnKeys(null));
        $this->assertSame([], ConfigColumnExtractor::allColumnKeys(null));
    }
}

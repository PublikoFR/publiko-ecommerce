<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter\Actions;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;
use Pko\AiImporter\Models\ImportJob;

class FeatureBuildActionTest extends TestCase
{
    private function ctx(array $row): ExecutionContext
    {
        return new ExecutionContext(job: new ImportJob, row: $row);
    }

    public function test_builds_hash_keyed_by_family_handle(): void
    {
        $action = Action::make([
            'type' => 'feature_build',
            'families' => [
                'marque' => ['col' => 'BRAND'],
                'matiere' => ['col' => 'MAT', 'values_map' => ['alu' => 'aluminium']],
            ],
        ]);

        $result = $action->execute(null, $this->ctx(['BRAND' => 'Somfy', 'MAT' => 'alu']));

        $this->assertSame([
            'marque' => ['somfy'],
            'matiere' => ['aluminium'],
        ], $result);
    }

    public function test_multi_value_splits_and_dedups(): void
    {
        $action = Action::make([
            'type' => 'feature_build',
            'families' => [
                'applications' => ['col' => 'USAGE', 'multi_value' => true, 'separator' => '|'],
            ],
        ]);

        $result = $action->execute(null, $this->ctx(['USAGE' => 'Résidentiel|Copropriété|Résidentiel']));

        $this->assertSame(['applications' => ['residentiel', 'copropriete']], $result);
    }

    public function test_empty_source_column_is_skipped(): void
    {
        $action = Action::make([
            'type' => 'feature_build',
            'families' => [
                'marque' => ['col' => 'BRAND'],
                'matiere' => ['col' => 'MAT'],
            ],
        ]);

        $result = $action->execute(null, $this->ctx(['BRAND' => 'Somfy', 'MAT' => '']));

        $this->assertSame(['marque' => ['somfy']], $result);
    }

    public function test_slugify_false_preserves_source_as_handle(): void
    {
        $action = Action::make([
            'type' => 'feature_build',
            'families' => [
                'marque' => ['col' => 'BRAND', 'slugify' => false],
            ],
        ]);

        $result = $action->execute(null, $this->ctx(['BRAND' => 'somfy-rts']));

        $this->assertSame(['marque' => ['somfy-rts']], $result);
    }
}

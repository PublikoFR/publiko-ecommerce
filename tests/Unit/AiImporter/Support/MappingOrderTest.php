<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter\Support;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Support\MappingOrder;

class MappingOrderTest extends TestCase
{
    public function test_llm_columns_run_after_plain_columns_and_follow_their_dependencies(): void
    {
        $mapping = [
            'name' => ['col' => 'A'],
            // `category` lit `manufacturer`, déclaré PLUS BAS dans le mapping.
            'category' => ['col' => 'G', 'actions' => [
                ['type' => 'llm_transform', 'input_columns' => [['col' => 'name'], 'manufacturer']],
            ]],
            // `features` lit `description`, colonne IA déclarée après elle.
            'features' => ['actions' => [
                ['type' => 'llm_transform', 'input_columns' => ['name', 'description']],
            ]],
            'manufacturer' => ['col' => 'B', 'actions' => [['type' => 'uppercase']]],
            'description' => ['col' => 'K', 'action' => ['type' => 'llm_transform', 'input_columns' => ['name']]],
        ];

        $this->assertSame(
            ['name', 'manufacturer', 'category', 'description', 'features'],
            array_keys(MappingOrder::sorted($mapping)),
        );
    }

    public function test_mapping_without_llm_is_untouched(): void
    {
        $mapping = ['b' => ['col' => 'B'], 'a' => ['col' => 'A']];

        $this->assertSame($mapping, MappingOrder::sorted($mapping));
    }

    public function test_cycle_between_llm_columns_falls_back_to_mapping_order(): void
    {
        $mapping = [
            'x' => ['actions' => [['type' => 'llm_transform', 'input_columns' => ['y']]]],
            'y' => ['actions' => [['type' => 'llm_transform', 'input_columns' => ['x']]]],
            'z' => ['col' => 'Z'],
        ];

        $this->assertSame(['z', 'x', 'y'], array_keys(MappingOrder::sorted($mapping)));
    }
}

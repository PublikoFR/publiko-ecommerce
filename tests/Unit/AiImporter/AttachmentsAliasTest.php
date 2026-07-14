<?php

declare(strict_types=1);

namespace Tests\Unit\AiImporter;

use PHPUnit\Framework\TestCase;
use Pko\AiImporter\Services\LunarProductWriter;

/**
 * Les configs PrestaShop émettent la clé `attachments` (notices/brochures).
 * Le writer la remappe vers la clé canonique `documents` (consommée par
 * syncProductDocuments → médiathèque + pko_product_documents).
 */
class AttachmentsAliasTest extends TestCase
{
    public function test_attachments_is_aliased_to_documents(): void
    {
        $out = LunarProductWriter::normalizeLegacyKeys([
            'reference' => 'SKU-1',
            'attachments' => '[{"type":"NOTICE","url":"https://x/y.pdf","name":"Notice"}]',
        ]);

        $this->assertArrayNotHasKey('attachments', $out);
        $this->assertArrayHasKey('documents', $out);
        $this->assertSame('[{"type":"NOTICE","url":"https://x/y.pdf","name":"Notice"}]', $out['documents']);
    }

    public function test_canonical_documents_wins_over_attachments(): void
    {
        $out = LunarProductWriter::normalizeLegacyKeys([
            'documents' => 'CANON',
            'attachments' => 'LEGACY',
        ]);

        $this->assertSame('CANON', $out['documents']);
        $this->assertArrayNotHasKey('attachments', $out);
    }
}

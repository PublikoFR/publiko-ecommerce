<?php

declare(strict_types=1);

namespace Tests\Unit\Pennylane;

use Illuminate\Http\Client\Factory as HttpFactory;
use PHPUnit\Framework\TestCase;
use Pko\Pennylane\Api\PennylaneClient;
use Pko\Pennylane\Api\Resources\CommercialDocumentsResource;
use Pko\Pennylane\Dto\CreateCommercialDocumentData;
use Pko\Pennylane\Dto\InvoiceLineData;

class CommercialDocumentsResourceTest extends TestCase
{
    private function resource(HttpFactory $http): CommercialDocumentsResource
    {
        return new CommercialDocumentsResource(new PennylaneClient($http, [
            'api_token' => 'test',
            'base_url' => 'https://app.pennylane.com/api/external/v2',
            'http' => ['timeout' => 5, 'retry_times' => 1, 'retry_sleep_ms' => 10],
        ]));
    }

    public function test_create_posts_to_commercial_documents(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*/commercial_documents' => $http::response(['id' => 88, 'document_number' => 'BL-0001'], 201),
        ]);

        $result = $this->resource($http)->create(['document_type' => 'shipping_order']);

        $this->assertSame('BL-0001', $result['document_number']);
        $http->assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/commercial_documents')
            && $request['document_type'] === 'shipping_order');
    }

    public function test_find_by_external_reference_returns_null_when_empty(): void
    {
        $http = new HttpFactory;
        $http->fake(['*/commercial_documents*' => $http::response(['items' => []], 200)]);

        $this->assertNull($this->resource($http)->findByExternalReference('delivery_1', 7, 'shipping_order'));
    }

    public function test_pdf_url_is_null_until_generated(): void
    {
        $http = new HttpFactory;
        $http->fake(['*/commercial_documents/88' => $http->sequence()
            ->push(['id' => 88, 'public_file_url' => null])
            ->push(['id' => 88, 'public_file_url' => 'https://files.example.test/bl.pdf'])]);

        $resource = $this->resource($http);

        $this->assertNull($resource->pdfUrl(88));
        $this->assertSame('https://files.example.test/bl.pdf', $resource->pdfUrl(88));
    }

    public function test_dto_serializes_required_fields(): void
    {
        $dto = new CreateCommercialDocumentData(
            documentType: CommercialDocumentsResource::TYPE_SHIPPING_ORDER,
            pennylaneCustomerId: 7,
            externalReference: 'delivery_42',
            date: '2026-10-08',
            deadline: '2026-10-08',
            currency: 'EUR',
            lines: [new InvoiceLineData(label: 'Produit', quantity: 2.0, unitAmount: 10.0, vatRate: 20.0)],
        );

        $payload = $dto->toArray();

        $this->assertSame('shipping_order', $payload['document_type']);
        $this->assertSame(7, $payload['customer_id']);
        $this->assertSame('2026-10-08', $payload['deadline']);
        $this->assertSame('FR_200', $payload['invoice_lines'][0]['vat_rate']);
        $this->assertArrayNotHasKey('pdf_invoice_subject', $payload);
    }
}

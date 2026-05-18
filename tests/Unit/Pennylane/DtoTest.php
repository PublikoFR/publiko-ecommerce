<?php

declare(strict_types=1);

namespace Tests\Unit\Pennylane;

use PHPUnit\Framework\TestCase;
use Pko\Pennylane\Dto\CreateCreditNoteData;
use Pko\Pennylane\Dto\CreateInvoiceData;
use Pko\Pennylane\Dto\CustomerData;
use Pko\Pennylane\Dto\InvoiceLineData;

class DtoTest extends TestCase
{
    public function test_invoice_line_formats_amount_with_2_decimals(): void
    {
        $line = new InvoiceLineData(label: 'Produit X', quantity: 2.0, unitAmount: 9.9, vatRate: 20.0);
        $array = $line->toArray();

        $this->assertSame('9.90', $array['currency_amount']);
        $this->assertSame(20.0, $array['vat_rate']);
    }

    public function test_create_invoice_strips_null_optional_fields(): void
    {
        $dto = new CreateInvoiceData(
            pennylaneCustomerId: 1,
            customerInvoiceTemplateId: 2,
            externalReference: 'order_42',
            date: '2026-04-22',
            deadline: '2026-04-22',
            currency: 'EUR',
            lines: [
                new InvoiceLineData('Ligne', 1.0, 100.0, 20.0),
            ],
        );

        $array = $dto->toArray();
        $this->assertArrayHasKey('customer_id', $array);
        $this->assertArrayHasKey('line_items', $array);
        $this->assertArrayNotHasKey('pdf_invoice_subject', $array);
        $this->assertArrayNotHasKey('pdf_description', $array);
    }

    public function test_create_credit_note_marks_credit_and_parent(): void
    {
        $dto = new CreateCreditNoteData(
            pennylaneCustomerId: 1,
            customerInvoiceTemplateId: 2,
            parentInvoicePennylaneId: 777,
            externalReference: 'refund_5',
            date: '2026-04-22',
            currency: 'EUR',
            lines: [new InvoiceLineData('Remb', 1.0, 50.0, 20.0)],
            reason: 'Remboursement',
        );

        $array = $dto->toArray();

        $this->assertTrue($array['credit_note']);
        $this->assertSame(777, $array['parent_invoice_id']);
        $this->assertSame('Remboursement', $array['pdf_description']);
    }

    public function test_customer_data_company_vs_individual(): void
    {
        $company = new CustomerData(
            externalReference: 'c_1', name: 'Acme SA', firstName: null, lastName: null,
            email: 'acme@x.com', vatNumber: 'FR123', siret: '123456789',
            phone: null, addressLine1: '1 rue', addressLine2: null,
            postalCode: '75000', city: 'Paris', countryAlpha2: 'FR', isCompany: true,
        );

        $arr = $company->toArray();
        $this->assertSame('company', $arr['customer_type']);
        $this->assertSame('FR123', $arr['vat_number']);
        $this->assertSame('123456789', $arr['reg_no']);
        $this->assertArrayNotHasKey('first_name', $arr);

        $individual = new CustomerData(
            externalReference: 'c_2', name: 'Jean Dupont', firstName: 'Jean', lastName: 'Dupont',
            email: 'j@x.com', vatNumber: null, siret: null,
            phone: null, addressLine1: null, addressLine2: null,
            postalCode: null, city: null, countryAlpha2: null, isCompany: false,
        );

        $arr = $individual->toArray();
        $this->assertSame('individual', $arr['customer_type']);
        $this->assertSame('Jean', $arr['first_name']);
        $this->assertArrayNotHasKey('vat_number', $arr);
    }
}

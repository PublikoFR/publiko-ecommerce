<?php

declare(strict_types=1);

namespace Tests\Unit\Pennylane;

use PHPUnit\Framework\TestCase;
use Pko\Pennylane\Api\Exceptions\PennylaneException;
use Pko\Pennylane\Dto\CreateCreditNoteData;
use Pko\Pennylane\Dto\CreateInvoiceData;
use Pko\Pennylane\Dto\CustomerData;
use Pko\Pennylane\Dto\InvoiceLineData;

class DtoTest extends TestCase
{
    public function test_invoice_line_uses_v2_price_and_vat_code(): void
    {
        $line = new InvoiceLineData(label: 'Produit X', quantity: 2.0, unitAmount: 9.9, vatRate: 20.0);
        $array = $line->toArray();

        $this->assertSame('9.90', $array['raw_currency_unit_price']);
        $this->assertSame('FR_200', $array['vat_rate']);
    }

    public function test_invoice_line_keeps_sub_cent_precision(): void
    {
        $line = new InvoiceLineData(label: 'Remisé', quantity: 3.0, unitAmount: 10 / 3, vatRate: 5.5);
        $array = $line->toArray();

        $this->assertSame('3.333333', $array['raw_currency_unit_price']);
        $this->assertSame('FR_55', $array['vat_rate']);
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
        $this->assertArrayHasKey('invoice_lines', $array);
        $this->assertSame('fr_FR', $array['language']);
        $this->assertArrayNotHasKey('pdf_invoice_subject', $array);
        $this->assertArrayNotHasKey('pdf_description', $array);
    }

    public function test_create_credit_note_has_no_v1_credit_fields(): void
    {
        $dto = new CreateCreditNoteData(
            pennylaneCustomerId: 1,
            customerInvoiceTemplateId: 2,
            parentInvoicePennylaneId: 777,
            externalReference: 'refund_5',
            date: '2026-04-22',
            currency: 'EUR',
            lines: [new InvoiceLineData('Remb', 1.0, -50.0, 20.0)],
            reason: 'Remboursement',
        );

        $array = $dto->toArray();

        // v2 : un avoir est une facture à montants négatifs, liée ensuite via link_credit_note.
        $this->assertArrayNotHasKey('credit_note', $array);
        $this->assertArrayNotHasKey('parent_invoice_id', $array);
        $this->assertSame('-50.00', $array['invoice_lines'][0]['raw_currency_unit_price']);
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
        $this->assertArrayNotHasKey('customer_type', $arr);
        $this->assertSame('c_1', $arr['external_reference']);
        $this->assertSame('fr_FR', $arr['billing_language']);
        $this->assertSame('FR123', $arr['vat_number']);
        $this->assertSame('123456789', $arr['reg_no']);
        $this->assertArrayNotHasKey('first_name', $arr);

        $individual = new CustomerData(
            externalReference: 'c_2', name: 'Jean Dupont', firstName: 'Jean', lastName: 'Dupont',
            email: 'j@x.com', vatNumber: null, siret: null,
            phone: null, addressLine1: '2 rue', addressLine2: 'Bât. B',
            postalCode: '69000', city: 'Lyon', countryAlpha2: 'FR', isCompany: false,
        );

        $arr = $individual->toArray();
        $this->assertSame('2 rue Bât. B', $arr['billing_address']['address']);
        $this->assertSame('Jean', $arr['first_name']);
        $this->assertArrayNotHasKey('vat_number', $arr);
    }

    public function test_customer_data_rejects_incomplete_billing_address(): void
    {
        $customer = new CustomerData(
            externalReference: 'c_3', name: 'Sans adresse', firstName: 'Paul', lastName: 'Martin',
            email: null, vatNumber: null, siret: null,
            phone: null, addressLine1: '3 rue', addressLine2: null,
            postalCode: null, city: 'Nantes', countryAlpha2: 'FR', isCompany: false,
        );

        $this->expectException(PennylaneException::class);
        $this->expectExceptionMessage('postal_code');

        $customer->toArray();
    }
}

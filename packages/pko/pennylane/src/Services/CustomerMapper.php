<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Api\Resources\CustomersResource;
use Pko\Pennylane\Dto\CustomerData;
use Pko\Pennylane\Models\PennylaneCustomer;

final class CustomerMapper
{
    public function __construct(private readonly CustomersResource $customers) {}

    public function resolveOrCreate(Order $order): int
    {
        $customer = $order->customer;
        $billing = $order->billingAddress ?: $order->shippingAddress;

        $externalReference = $this->externalReference($order);

        $mapping = $customer
            ? PennylaneCustomer::where('lunar_customer_id', $customer->id)->first()
            : PennylaneCustomer::where('external_reference', $externalReference)->first();

        if ($mapping && $mapping->pennylane_customer_id) {
            return (int) $mapping->pennylane_customer_id;
        }

        $dto = $this->buildDto($order, $customer, $billing, $externalReference);

        $existing = $this->customers->findByExternalReference($externalReference);
        $pennylaneId = $existing['id'] ?? null;

        if (! $pennylaneId) {
            try {
                $created = $this->customers->create($dto->toArray());
                $pennylaneId = $created['id'] ?? null;
            } catch (PennylaneApiException $e) {
                if ($e->status === 409) {
                    $retry = $this->customers->findByExternalReference($externalReference);
                    $pennylaneId = $retry['id'] ?? null;
                } else {
                    throw $e;
                }
            }
        }

        if (! $pennylaneId) {
            throw new \RuntimeException('Impossible de résoudre le customer Pennylane (pas d\'id retourné).');
        }

        PennylaneCustomer::updateOrCreate(
            $customer
                ? ['lunar_customer_id' => $customer->id]
                : ['external_reference' => $externalReference],
            [
                'external_reference' => $externalReference,
                'pennylane_customer_id' => $pennylaneId,
                'payload_snapshot' => $dto->toArray(),
                'synced_at' => Carbon::now(),
            ],
        );

        return (int) $pennylaneId;
    }

    private function externalReference(Order $order): string
    {
        $prefix = (string) config('pennylane.external_reference_prefix.customer', 'lunar_cust_');

        return $order->customer_id
            ? $prefix.$order->customer_id
            : $prefix.'order_'.$order->id;
    }

    private function buildDto(
        Order $order,
        ?Customer $customer,
        ?OrderAddress $billing,
        string $externalReference,
    ): CustomerData {
        $company = $customer?->company_name ?: ($billing?->company_name ?? null);
        $isCompany = ! empty($company);

        $firstName = $billing?->first_name ?? $customer?->first_name;
        $lastName = $billing?->last_name ?? $customer?->last_name;
        $name = $isCompany
            ? $company
            : trim(($firstName ?? '').' '.($lastName ?? ''));

        if ($name === '') {
            $name = $billing?->contact_email ?? 'Client '.$order->id;
        }

        return new CustomerData(
            externalReference: $externalReference,
            name: $name,
            firstName: $firstName,
            lastName: $lastName,
            email: $billing?->contact_email ?: $this->userEmail($order),
            vatNumber: $customer?->tax_identifier ?? $billing?->tax_identifier,
            siret: $customer?->account_ref ?? null,
            phone: $billing?->contact_phone,
            addressLine1: $billing?->line_one,
            addressLine2: $billing?->line_two,
            postalCode: $billing?->postcode,
            city: $billing?->city,
            countryAlpha2: $billing?->country?->iso2,
            isCompany: $isCompany,
        );
    }

    private function userEmail(Order $order): ?string
    {
        try {
            return $order->user?->email;
        } catch (\Throwable) {
            return null;
        }
    }
}

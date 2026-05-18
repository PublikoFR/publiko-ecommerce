<?php

declare(strict_types=1);

namespace Pko\Pennylane\Dto;

final class CustomerData
{
    public function __construct(
        public readonly string $externalReference,
        public readonly string $name,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $email,
        public readonly ?string $vatNumber,
        public readonly ?string $siret,
        public readonly ?string $phone,
        public readonly ?string $addressLine1,
        public readonly ?string $addressLine2,
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly ?string $countryAlpha2,
        public readonly bool $isCompany,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'source_id' => $this->externalReference,
            'customer_type' => $this->isCompany ? 'company' : 'individual',
            'name' => $this->name,
            'emails' => array_values(array_filter([$this->email])),
            'phone' => $this->phone,
            'billing_address' => array_filter([
                'address' => $this->addressLine1,
                'postal_code' => $this->postalCode,
                'city' => $this->city,
                'country_alpha2' => $this->countryAlpha2,
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        if ($this->isCompany) {
            $payload['reg_no'] = $this->siret;
            $payload['vat_number'] = $this->vatNumber;
        } else {
            $payload['first_name'] = $this->firstName;
            $payload['last_name'] = $this->lastName;
        }

        return array_filter($payload, fn ($v) => $v !== null && $v !== [] && $v !== '');
    }
}

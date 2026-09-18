<?php

declare(strict_types=1);

namespace Pko\Pennylane\Dto;

use Pko\Pennylane\Api\Exceptions\PennylaneException;
use Pko\Pennylane\Support\Language;

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
        public readonly string $language = 'fr',
    ) {}

    /**
     * Payload des endpoints `/company_customers` et `/individual_customers`
     * (v2 : un endpoint par type, plus de `customer_type` dans le corps).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'external_reference' => $this->externalReference,
            'emails' => array_values(array_filter([$this->email])),
            'phone' => $this->phone,
            'billing_address' => $this->billingAddress(),
            'billing_language' => Language::toPennylane($this->language),
        ];

        if ($this->isCompany) {
            $payload['name'] = $this->name;
            $payload['reg_no'] = $this->siret;
            $payload['vat_number'] = $this->vatNumber;
        } else {
            // Les deux champs sont obligatoires côté API : à défaut de prénom
            // et nom distincts, le nom complet sert de nom de famille.
            $payload['first_name'] = $this->firstName ?: '-';
            $payload['last_name'] = $this->lastName ?: $this->name;
        }

        return array_filter($payload, fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * Les quatre champs sont obligatoires : une adresse partielle est refusée
     * en 422 par l'API, autant l'intercepter avec un message lisible.
     *
     * @return array<string,string>
     */
    private function billingAddress(): array
    {
        $address = [
            'address' => trim(implode(' ', array_filter([$this->addressLine1, $this->addressLine2]))),
            'postal_code' => (string) $this->postalCode,
            'city' => (string) $this->city,
            'country_alpha2' => (string) $this->countryAlpha2,
        ];

        $missing = array_keys(array_filter($address, fn (string $v) => trim($v) === ''));
        if ($missing !== []) {
            throw new PennylaneException('Adresse de facturation incomplète pour le client Pennylane ('.implode(', ', $missing).').');
        }

        return $address;
    }
}

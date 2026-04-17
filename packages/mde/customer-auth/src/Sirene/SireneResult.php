<?php

declare(strict_types=1);

namespace Mde\CustomerAuth\Sirene;

final readonly class SireneResult
{
    public function __construct(
        public Status $status,
        public string $siret,
        public ?string $raisonSociale = null,
        public ?string $nafCode = null,
        public ?string $nafLabel = null,
        public ?string $addressLine1 = null,
        public ?string $postcode = null,
        public ?string $city = null,
        public ?string $category = null,
    ) {}

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }

    public function isInactive(): bool
    {
        return $this->status === Status::Inactive;
    }

    public function isPending(): bool
    {
        return $this->status === Status::Pending;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'siret' => $this->siret,
            'raison_sociale' => $this->raisonSociale,
            'naf_code' => $this->nafCode,
            'naf_label' => $this->nafLabel,
            'address_line_1' => $this->addressLine1,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'category' => $this->category,
        ];
    }
}

<?php

namespace App\DataTransferObjects;

class FacilityData
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly string $name = '',
        public readonly ?string $address = null,
        public readonly ?string $addressLine1 = null,
        public readonly ?string $addressLine2 = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $zip = null,
        public readonly ?string $phone = null,
        public readonly ?string $fax = null,
        public readonly ?string $email = null,
        public readonly ?string $npi = null,
        public readonly ?string $taxId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'email' => $this->email,
            'npi' => $this->npi,
            'tax_id' => $this->taxId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? '',
            address: $data['address'] ?? null,
            addressLine1: $data['address_line1'] ?? null,
            addressLine2: $data['address_line2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zip: $data['zip'] ?? null,
            phone: $data['phone'] ?? null,
            fax: $data['fax'] ?? null,
            email: $data['email'] ?? null,
            npi: $data['npi'] ?? null,
            taxId: $data['tax_id'] ?? null,
        );
    }
}

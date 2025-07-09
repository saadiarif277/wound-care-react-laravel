<?php

namespace App\DataTransferObjects;

class ProviderData
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly string $name = '',
        public readonly ?string $npi = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $specialty = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'npi' => $this->npi,
            'email' => $this->email,
            'phone' => $this->phone,
            'specialty' => $this->specialty,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? '',
            npi: $data['npi'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            specialty: $data['specialty'] ?? null,
        );
    }
}

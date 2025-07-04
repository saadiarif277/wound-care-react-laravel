<?php

namespace App\DataTransferObjects;

class PatientData
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $dateOfBirth,
        public readonly string $gender,
        public readonly ?string $memberId = null,
        public readonly ?string $displayId = null,
        public readonly ?string $addressLine1 = null,
        public readonly ?string $addressLine2 = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $zip = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly bool $isSubscriber = true,
    ) {}

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'member_id' => $this->memberId,
            'display_id' => $this->displayId,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_subscriber' => $this->isSubscriber,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'] ?? '',
            lastName: $data['last_name'] ?? '',
            dateOfBirth: $data['date_of_birth'] ?? '',
            gender: $data['gender'] ?? 'unknown',
            memberId: $data['member_id'] ?? null,
            displayId: $data['display_id'] ?? null,
            addressLine1: $data['address_line1'] ?? null,
            addressLine2: $data['address_line2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zip: $data['zip'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            isSubscriber: $data['is_subscriber'] ?? true,
        );
    }
} 
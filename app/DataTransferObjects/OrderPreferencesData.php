<?php

namespace App\DataTransferObjects;

class OrderPreferencesData
{
    public function __construct(
        public readonly string $expectedServiceDate = '',
        public readonly string $shippingSpeed = 'standard',
        public readonly string $placeOfService = '',
        public readonly ?string $deliveryInstructions = null,
    ) {}

    public function toArray(): array
    {
        return [
            'expected_service_date' => $this->expectedServiceDate,
            'shipping_speed' => $this->shippingSpeed,
            'place_of_service' => $this->placeOfService,
            'delivery_instructions' => $this->deliveryInstructions,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            expectedServiceDate: $data['expected_service_date'] ?? '',
            shippingSpeed: $data['shipping_speed'] ?? 'standard',
            placeOfService: $data['place_of_service'] ?? '',
            deliveryInstructions: $data['delivery_instructions'] ?? null,
        );
    }
} 
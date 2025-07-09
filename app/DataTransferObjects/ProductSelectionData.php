<?php

namespace App\DataTransferObjects;

class ProductSelectionData
{
    public function __construct(
        public readonly array $selectedProducts = [],
        public readonly ?int $manufacturerId = null,
        public readonly ?string $manufacturerName = null,
    ) {}

    public function getProductIds(): array
    {
        return array_column($this->selectedProducts, 'product_id');
    }

    public function getTotalQuantity(): int
    {
        return array_sum(array_column($this->selectedProducts, 'quantity'));
    }

    public function hasProducts(): bool
    {
        return !empty($this->selectedProducts);
    }

    public function toArray(): array
    {
        return [
            'selected_products' => $this->selectedProducts,
            'manufacturer_id' => $this->manufacturerId,
            'manufacturer_name' => $this->manufacturerName,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            selectedProducts: $data['selected_products'] ?? [],
            manufacturerId: $data['manufacturer_id'] ?? null,
            manufacturerName: $data['manufacturer_name'] ?? null,
        );
    }
}

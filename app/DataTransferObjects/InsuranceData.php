<?php

namespace App\DataTransferObjects;

class InsuranceData
{
    public function __construct(
        public readonly string $primaryName = '',
        public readonly string $primaryMemberId = '',
        public readonly string $primaryPlanType = '',
        public readonly bool $hasSecondary = false,
        public readonly ?string $secondaryName = null,
        public readonly ?string $secondaryMemberId = null,
        public readonly ?string $secondaryPlanType = null,
    ) {}

    public function toArray(): array
    {
        return [
            'primary_name' => $this->primaryName,
            'primary_member_id' => $this->primaryMemberId,
            'primary_plan_type' => $this->primaryPlanType,
            'has_secondary' => $this->hasSecondary,
            'secondary_name' => $this->secondaryName,
            'secondary_member_id' => $this->secondaryMemberId,
            'secondary_plan_type' => $this->secondaryPlanType,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            primaryName: $data['primary_name'] ?? '',
            primaryMemberId: $data['primary_member_id'] ?? '',
            primaryPlanType: $data['primary_plan_type'] ?? '',
            hasSecondary: $data['has_secondary'] ?? false,
            secondaryName: $data['secondary_name'] ?? null,
            secondaryMemberId: $data['secondary_member_id'] ?? null,
            secondaryPlanType: $data['secondary_plan_type'] ?? null,
        );
    }
} 
<?php

namespace App\DataTransferObjects;

class ClinicalData
{
    public function __construct(
        public readonly string $woundType = '',
        public readonly string $woundLocation = '',
        public readonly float $woundSizeLength = 0,
        public readonly float $woundSizeWidth = 0,
        public readonly ?float $woundSizeDepth = null,
        public readonly array $diagnosisCodes = [],
        public readonly ?string $clinicalNotes = null,
    ) {}

    public function getWoundArea(): float
    {
        return $this->woundSizeLength * $this->woundSizeWidth;
    }

    public function toArray(): array
    {
        return [
            'wound_type' => $this->woundType,
            'wound_location' => $this->woundLocation,
            'wound_size_length' => $this->woundSizeLength,
            'wound_size_width' => $this->woundSizeWidth,
            'wound_size_depth' => $this->woundSizeDepth,
            'diagnosis_codes' => $this->diagnosisCodes,
            'clinical_notes' => $this->clinicalNotes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            woundType: $data['wound_type'] ?? '',
            woundLocation: $data['wound_location'] ?? '',
            woundSizeLength: (float) ($data['wound_size_length'] ?? 0),
            woundSizeWidth: (float) ($data['wound_size_width'] ?? 0),
            woundSizeDepth: isset($data['wound_size_depth']) ? (float) $data['wound_size_depth'] : null,
            diagnosisCodes: $data['diagnosis_codes'] ?? [],
            clinicalNotes: $data['clinical_notes'] ?? null,
        );
    }
} 
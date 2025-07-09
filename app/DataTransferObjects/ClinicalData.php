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
        public readonly ?int $woundDurationWeeks = null,
        public readonly array $diagnosisCodes = [],
        public readonly string $primaryDiagnosisCode = '',
        public readonly string $secondaryDiagnosisCode = '',
        public readonly array $applicationCptCodes = [],
        public readonly ?string $clinicalNotes = null,
        public readonly bool $failedConservativeTreatment = false,
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
            'wound_duration_weeks' => $this->woundDurationWeeks,
            'diagnosis_codes' => $this->diagnosisCodes,
            'primary_diagnosis_code' => $this->primaryDiagnosisCode,
            'secondary_diagnosis_code' => $this->secondaryDiagnosisCode,
            'application_cpt_codes' => $this->applicationCptCodes,
            'clinical_notes' => $this->clinicalNotes,
            'failed_conservative_treatment' => $this->failedConservativeTreatment,
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
            woundDurationWeeks: isset($data['wound_duration_weeks']) ? (int) $data['wound_duration_weeks'] : null,
            diagnosisCodes: $data['diagnosis_codes'] ?? [],
            primaryDiagnosisCode: $data['primary_diagnosis_code'] ?? '',
            secondaryDiagnosisCode: $data['secondary_diagnosis_code'] ?? '',
            applicationCptCodes: $data['application_cpt_codes'] ?? [],
            clinicalNotes: $data['clinical_notes'] ?? null,
            failedConservativeTreatment: $data['failed_conservative_treatment'] ?? false,
        );
    }
}

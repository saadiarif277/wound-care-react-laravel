<?php

namespace App\Services\HealthData\DTO;

class ChecklistValidationResult
{
    public bool $isValid;
    public array $errors;
    public array $warnings;
    public array $missingFields;
    public int $macComplianceScore; // 0-100

    public function __construct(
        bool $isValid,
        array $errors,
        array $warnings,
        array $missingFields,
        int $macComplianceScore
    ) {
        $this->isValid = $isValid;
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->missingFields = $missingFields;
        $this->macComplianceScore = $macComplianceScore;
    }

    public function toArray(): array
    {
        return [
            'isValid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'missingFields' => $this->missingFields,
            'macComplianceScore' => $this->macComplianceScore,
        ];
    }
} 
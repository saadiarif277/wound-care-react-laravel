<?php

namespace App\Services\HealthData\Services;

use App\Services\HealthData\DTO\SkinSubstituteChecklistInput;
use App\Services\HealthData\DTO\ChecklistValidationResult; // Assuming DTO for result will be created

class ChecklistValidationService
{
    /**
     * Validates checklist data against MAC requirements before FHIR submission.
     * This is a port of the TypeScript validation logic.
     */
    public function validateSkinSubstituteChecklist(
        SkinSubstituteChecklistInput $data
    ): ChecklistValidationResult {
        $errors = [];
        $warnings = [];
        $missingFields = [];

        // Required field validation (from DTO which assumes these top-level fields are always submitted)
        if (empty($data->patientName)) $missingFields[] = 'Patient Name';
        if (empty($data->dateOfBirth)) $missingFields[] = 'Date of Birth';
        if (empty($data->dateOfProcedure)) $missingFields[] = 'Date of Procedure';

        // Diagnosis related - assuming ulcerLocation is the primary wound site for context
        if (empty($data->ulcerLocation)) $missingFields[] = 'Specific Ulcer Location';

        // Diabetes-specific validation (referencing DTO properties directly)
        if ($data->hasDiabetes) {
            if (empty($data->diabetesType)) {
                // errors[] = 'Diabetes type not specified if hasDiabetes is true.'; // Stricter than warning
                $warnings[] = 'Diabetes type not specified despite indication of diabetes.';
            }
            if ($data->hba1cResult === null) { // Check against null as 0 is a valid result
                $errors[] = 'HbA1c result required for diabetic patients.';
            } elseif ($data->hba1cResult > 10) { // Example threshold
                $warnings[] = 'HbA1c >10% may indicate poor glycemic control.';
            }
            if (empty($data->hba1cDate) && $data->hba1cResult !== null) {
                 $errors[] = 'Date of HbA1c lab is required if result is provided.';
            }
        }

        // Wound measurement validation
        if (!isset($data->length) || !isset($data->width)) { // Check isset for numeric 0 being valid
            $errors[] = 'Wound measurements (length and width) are required.';
        }
        if (!isset($data->woundDepth)) {
             $errors[] = 'Numeric wound depth is required.';
        }
        if (empty($data->depth)) { // This is 'full-thickness' or 'partial-thickness'
            $errors[] = 'Wound depth classification (full/partial) is required.';
        }
        if (empty($data->ulcerDuration)) $errors[] = 'Ulcer duration is required.';


        // Conservative care validation
        if (!$data->debridementPerformed && !$data->moistDressingsApplied) {
            $warnings[] = 'At least one form of standard conservative care (debridement, moist dressings) should be documented.';
        }

        // Circulation assessment for foot ulcers (example from TS, adapt to DTO fields)
        $ulcerLocationLower = strtolower($data->ulcerLocation ?? '');
        if ($data->hasDiabetes && (strpos($ulcerLocationLower, 'foot') !== false || strpos($ulcerLocationLower, 'toe') !== false)) {
            if ($data->abiResult === null && $data->tcpo2Result === null) {
                $warnings[] = 'Vascular assessment (ABI or TcPO2) is recommended for diabetic foot ulcers.';
            }
        }
        
        // Basic checks from DTO booleans (these are required by FormRequest, this is more for MAC logic if needed)
        if(!isset($data->hasInfection)) $missingFields[] = 'Indication for Infection/Osteomyelitis';
        if(!isset($data->hasNecroticTissue)) $missingFields[] = 'Indication for Necrotic Tissue';
        if(!isset($data->hasCharcotDeformity)) $missingFields[] = 'Indication for Active Charcot Deformity';
        if(!isset($data->hasMalignancy)) $missingFields[] = 'Indication for Malignancy';
        if(!isset($data->hasTriphasicWaveforms)) $missingFields[] = 'Indication for Doppler Waveforms';
        // ... continue for other boolean fields from conservative treatment...

        // Calculate MAC compliance score (example logic)
        $totalChecks = 15; // Example total number of critical MAC data points
        $passedChecks = $totalChecks - count($errors) - count($missingFields) - (count($warnings) * 0.5);
        $macComplianceScore = max(0, min(100, ($passedChecks / ($totalChecks ?: 1)) * 100)); // Avoid division by zero

        return new ChecklistValidationResult(
            empty($errors) && empty($missingFields), // isValid only if no hard errors or missing critical fields
            $errors,
            $warnings,
            $missingFields,
            (int)round($macComplianceScore)
        );
    }
} 
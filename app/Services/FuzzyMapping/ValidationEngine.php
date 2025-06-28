<?php

namespace App\Services\FuzzyMapping;

use Illuminate\Support\Facades\Log;

class ValidationEngine
{
    protected ManufacturerTemplateHandler $templateHandler;
    
    public function __construct(ManufacturerTemplateHandler $templateHandler)
    {
        $this->templateHandler = $templateHandler;
    }
    
    /**
     * Validate mapped data against manufacturer requirements
     */
    public function validateMappedData(
        array $mappedData,
        string $manufacturerName,
        string $templateName
    ): array {
        $errors = [];
        $warnings = [];
        $valid = true;
        
        // Get manufacturer validation rules
        $validationRules = $this->templateHandler->getValidationRulesForManufacturer($manufacturerName, $templateName);
        
        foreach ($mappedData as $fieldName => $data) {
            // Check if field was successfully mapped
            if (($data['strategy'] ?? '') === 'unmappable') {
                // Check if this is a required field
                if ($this->isRequiredField($fieldName, $validationRules)) {
                    $errors[$fieldName][] = "Required field could not be mapped";
                    $valid = false;
                } else {
                    $warnings[$fieldName][] = "Optional field could not be mapped";
                }
                continue;
            }
            
            // Validate field value
            $fieldValidation = $this->validateField($fieldName, $data['value'] ?? '', $validationRules);
            
            if (!$fieldValidation['valid']) {
                $errors[$fieldName] = $fieldValidation['errors'];
                $valid = false;
            }
            
            if (!empty($fieldValidation['warnings'])) {
                $warnings[$fieldName] = $fieldValidation['warnings'];
            }
            
            // Check confidence score
            if (($data['confidence'] ?? 0) < 0.5) {
                $warnings[$fieldName][] = "Low confidence mapping ({$data['confidence']})";
            }
        }
        
        // Check for missing required fields
        $requiredFields = $this->getRequiredFields($validationRules);
        foreach ($requiredFields as $requiredField) {
            if (!isset($mappedData[$requiredField])) {
                $errors[$requiredField][] = "Required field missing from mapping";
                $valid = false;
            }
        }
        
        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'total_fields' => count($mappedData),
                'error_count' => count($errors),
                'warning_count' => count($warnings),
                'required_fields_missing' => count(array_intersect($requiredFields, array_keys($errors)))
            ]
        ];
    }
    
    /**
     * Validate individual field value
     */
    protected function validateField(string $fieldName, $value, array $validationRules): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];
        
        if (!isset($validationRules[$fieldName])) {
            return $result;
        }
        
        $rules = $validationRules[$fieldName];
        
        // Check required
        if (($rules['required'] ?? false) && empty($value)) {
            $result['valid'] = false;
            $result['errors'][] = "Required field is empty";
            return $result;
        }
        
        // Check data type
        if (isset($rules['type'])) {
            if (!$this->validateType($value, $rules['type'])) {
                $result['valid'] = false;
                $result['errors'][] = "Invalid data type. Expected: {$rules['type']}";
            }
        }
        
        // Check format/pattern
        if (isset($rules['pattern']) && !empty($value)) {
            if (!preg_match($rules['pattern'], $value)) {
                $result['valid'] = false;
                $result['errors'][] = "Value does not match required format";
            }
        }
        
        // Check length constraints
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            $result['valid'] = false;
            $result['errors'][] = "Value exceeds maximum length of {$rules['max_length']}";
        }
        
        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            $result['valid'] = false;
            $result['errors'][] = "Value is shorter than minimum length of {$rules['min_length']}";
        }
        
        // Check allowed values
        if (isset($rules['allowed_values']) && !in_array($value, $rules['allowed_values'])) {
            $result['valid'] = false;
            $result['errors'][] = "Value not in allowed list: " . implode(', ', $rules['allowed_values']);
        }
        
        return $result;
    }
    
    /**
     * Validate data type
     */
    protected function validateType($value, string $expectedType): bool
    {
        switch ($expectedType) {
            case 'string':
                return is_string($value);
            case 'numeric':
                return is_numeric($value);
            case 'date':
                return strtotime($value) !== false;
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'phone':
                return preg_match('/^[\d\s\-\(\)\+]+$/', $value);
            case 'boolean':
                return in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no']);
            default:
                return true;
        }
    }
    
    /**
     * Check if field is required
     */
    protected function isRequiredField(string $fieldName, array $validationRules): bool
    {
        return $validationRules[$fieldName]['required'] ?? false;
    }
    
    /**
     * Get list of required fields
     */
    protected function getRequiredFields(array $validationRules): array
    {
        $required = [];
        foreach ($validationRules as $fieldName => $rules) {
            if ($rules['required'] ?? false) {
                $required[] = $fieldName;
            }
        }
        return $required;
    }
    
    /**
     * Validate complete IVR submission
     */
    public function validateIVRSubmission(array $data, string $manufacturerName): array
    {
        $errors = [];
        $warnings = [];
        
        // Manufacturer-specific validation
        switch (strtolower($manufacturerName)) {
            case 'convatec':
                return $this->validateConvatecSubmission($data);
            case 'mölnlycke':
            case 'molnlycke':
                return $this->validateMolnlyckeSubmission($data);
            case 'hollister':
                return $this->validateHollisterSubmission($data);
            default:
                return $this->validateGenericSubmission($data);
        }
    }
    
    /**
     * ConvaTec specific validation
     */
    protected function validateConvatecSubmission(array $data): array
    {
        $errors = [];
        $warnings = [];
        $valid = true;
        
        // ConvaTec requires specific insurance information format
        if (!empty($data['insurance_member_id'])) {
            if (!preg_match('/^[A-Za-z0-9]{6,20}$/', $data['insurance_member_id'])) {
                $warnings['insurance_member_id'][] = "Member ID format may not meet ConvaTec requirements";
            }
        }
        
        // Check for ConvaTec-specific required fields
        $convatecRequired = ['patient_name', 'patient_dob', 'insurance_name', 'insurance_member_id'];
        foreach ($convatecRequired as $field) {
            if (empty($data[$field])) {
                $errors[$field][] = "Required by ConvaTec";
                $valid = false;
            }
        }
        
        return ['valid' => $valid, 'errors' => $errors, 'warnings' => $warnings];
    }
    
    /**
     * Mölnlycke specific validation
     */
    protected function validateMolnlyckeSubmission(array $data): array
    {
        $errors = [];
        $warnings = [];
        $valid = true;
        
        // Mölnlycke requires diagnosis codes
        if (empty($data['diagnosis_codes'])) {
            $errors['diagnosis_codes'][] = "Mölnlycke requires at least one diagnosis code";
            $valid = false;
        }
        
        // Validate diagnosis code format
        if (!empty($data['diagnosis_codes'])) {
            $codes = is_array($data['diagnosis_codes']) ? $data['diagnosis_codes'] : [$data['diagnosis_codes']];
            foreach ($codes as $code) {
                if (!preg_match('/^[A-Z]\d{2,3}(\.\d{1,2})?$/', $code)) {
                    $warnings['diagnosis_codes'][] = "Diagnosis code format may be invalid: $code";
                }
            }
        }
        
        return ['valid' => $valid, 'errors' => $errors, 'warnings' => $warnings];
    }
    
    /**
     * Hollister specific validation
     */
    protected function validateHollisterSubmission(array $data): array
    {
        $errors = [];
        $warnings = [];
        $valid = true;
        
        // Hollister requires specific date format
        if (!empty($data['patient_dob'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $data['patient_dob']);
            if (!$date || $date->format('Y-m-d') !== $data['patient_dob']) {
                $errors['patient_dob'][] = "Hollister requires date in YYYY-MM-DD format";
                $valid = false;
            }
        }
        
        return ['valid' => $valid, 'errors' => $errors, 'warnings' => $warnings];
    }
    
    /**
     * Generic validation for other manufacturers
     */
    protected function validateGenericSubmission(array $data): array
    {
        $errors = [];
        $warnings = [];
        $valid = true;
        
        // Basic required fields
        $basicRequired = ['patient_name', 'patient_dob', 'insurance_name'];
        foreach ($basicRequired as $field) {
            if (empty($data[$field])) {
                $errors[$field][] = "Required field is missing";
                $valid = false;
            }
        }
        
        return ['valid' => $valid, 'errors' => $errors, 'warnings' => $warnings];
    }
}
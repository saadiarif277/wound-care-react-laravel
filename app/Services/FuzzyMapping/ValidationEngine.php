<?php

namespace App\Services\FuzzyMapping;

use App\Models\IVRTemplateField;
use App\Models\Manufacturer;
use Illuminate\Support\Facades\Log;

class ValidationEngine
{
    protected ManufacturerTemplateHandler $templateHandler;
    
    protected array $globalValidationRules = [
        'patient_first_name' => ['required', 'min:2', 'max:50', 'alpha_spaces'],
        'patient_last_name' => ['required', 'min:2', 'max:50', 'alpha_spaces'],
        'patient_dob' => ['required', 'date', 'before:today', 'after:1900-01-01'],
        'provider_npi' => ['required', 'numeric', 'digits:10', 'npi'],
        'facility_npi' => ['numeric', 'digits:10', 'npi'],
        'insurance_id' => ['required', 'alphanumeric', 'max:30'],
        'ssn' => ['numeric', 'digits:9'],
        'phone' => ['phone_us'],
        'email' => ['email'],
        'zip_code' => ['numeric', 'regex:/^\d{5}(-\d{4})?$/'],
    ];

    protected array $conditionalRules = [
        'medicare_number' => [
            'when' => ['insurance_type' => 'Medicare'],
            'rules' => ['required', 'medicare_id'],
        ],
        'secondary_insurance_id' => [
            'when' => ['has_secondary_insurance' => true],
            'rules' => ['required', 'alphanumeric'],
        ],
        'wound_depth' => [
            'when' => ['wound_stage' => ['3', '4', 'unstageable']],
            'rules' => ['required', 'numeric', 'min:0'],
        ],
    ];

    public function __construct(ManufacturerTemplateHandler $templateHandler)
    {
        $this->templateHandler = $templateHandler;
    }

    public function validateMappedData(
        array $mappedData,
        string $manufacturerName,
        string $templateName
    ): array {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'missing_required' => [],
        ];

        // Get required fields for this template
        $requiredFields = $this->templateHandler->getRequiredFields($manufacturerName, $templateName);
        
        // Check for missing required fields
        foreach ($requiredFields as $field) {
            if (!isset($mappedData[$field]) || empty($mappedData[$field])) {
                $results['missing_required'][] = $field;
                $results['valid'] = false;
            }
        }

        // Validate each mapped field
        foreach ($mappedData as $fieldName => $fieldData) {
            $value = $fieldData['value'] ?? null;
            
            // Apply global validation rules
            if (isset($this->globalValidationRules[$fieldName])) {
                $validation = $this->validateField($fieldName, $value, $this->globalValidationRules[$fieldName]);
                if (!$validation['valid']) {
                    $results['errors'][$fieldName] = $validation['errors'];
                    $results['valid'] = false;
                }
            }
            
            // Apply manufacturer-specific validation
            $manufacturerValidation = $this->templateHandler->validateFieldValue(
                $manufacturerName,
                $fieldName,
                $value
            );
            if (!$manufacturerValidation['valid']) {
                $results['errors'][$fieldName] = array_merge(
                    $results['errors'][$fieldName] ?? [],
                    $manufacturerValidation['errors']
                );
                $results['valid'] = false;
            }
            
            // Apply conditional validation
            $conditionalValidation = $this->validateConditional($fieldName, $value, $mappedData);
            if (!$conditionalValidation['valid']) {
                $results['errors'][$fieldName] = array_merge(
                    $results['errors'][$fieldName] ?? [],
                    $conditionalValidation['errors']
                );
                $results['valid'] = false;
            }
            
            // Check confidence warnings
            if (isset($fieldData['confidence']) && $fieldData['confidence'] < 0.8) {
                $results['warnings'][$fieldName] = sprintf(
                    'Low confidence mapping (%.0f%%). Please verify this field.',
                    $fieldData['confidence'] * 100
                );
            }
        }

        // Cross-field validation
        $crossFieldValidation = $this->validateCrossFields($mappedData);
        if (!$crossFieldValidation['valid']) {
            $results['errors'] = array_merge($results['errors'], $crossFieldValidation['errors']);
            $results['valid'] = false;
        }

        return $results;
    }

    protected function validateField(string $fieldName, $value, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];
                
                if (!$this->checkRule($ruleName, $value, $ruleParams)) {
                    $errors[] = $this->getErrorMessage($fieldName, $ruleName, $ruleParams);
                }
            }
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    protected function checkRule(string $rule, $value, array $params = []): bool
    {
        switch ($rule) {
            case 'required':
                return !empty($value);
                
            case 'numeric':
                return is_numeric($value);
                
            case 'alpha':
                return ctype_alpha(str_replace(' ', '', $value));
                
            case 'alpha_spaces':
                return preg_match('/^[a-zA-Z\s]+$/', $value);
                
            case 'alphanumeric':
                return ctype_alnum(str_replace([' ', '-', '_'], '', $value));
                
            case 'digits':
                $digits = preg_replace('/[^0-9]/', '', $value);
                return strlen($digits) == ($params[0] ?? 0);
                
            case 'min':
                return strlen($value) >= ($params[0] ?? 0);
                
            case 'max':
                return strlen($value) <= ($params[0] ?? PHP_INT_MAX);
                
            case 'date':
                return strtotime($value) !== false;
                
            case 'before':
                $date = strtotime($value);
                $compare = $params[0] === 'today' ? time() : strtotime($params[0]);
                return $date && $date < $compare;
                
            case 'after':
                $date = strtotime($value);
                $compare = strtotime($params[0]);
                return $date && $date > $compare;
                
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                
            case 'phone_us':
                $numeric = preg_replace('/[^0-9]/', '', $value);
                return strlen($numeric) === 10 || strlen($numeric) === 11;
                
            case 'npi':
                return $this->validateNPI($value);
                
            case 'medicare_id':
                return $this->validateMedicareId($value);
                
            case 'regex':
                return preg_match($params[0], $value);
                
            default:
                return true;
        }
    }

    protected function validateConditional(string $fieldName, $value, array $allData): array
    {
        if (!isset($this->conditionalRules[$fieldName])) {
            return ['valid' => true, 'errors' => []];
        }
        
        $conditional = $this->conditionalRules[$fieldName];
        $shouldValidate = false;
        
        // Check conditions
        foreach ($conditional['when'] as $condField => $condValue) {
            $actualValue = $allData[$condField]['value'] ?? null;
            
            if (is_array($condValue)) {
                if (in_array($actualValue, $condValue)) {
                    $shouldValidate = true;
                    break;
                }
            } else {
                if ($actualValue == $condValue) {
                    $shouldValidate = true;
                    break;
                }
            }
        }
        
        if ($shouldValidate) {
            return $this->validateField($fieldName, $value, $conditional['rules']);
        }
        
        return ['valid' => true, 'errors' => []];
    }

    protected function validateCrossFields(array $mappedData): array
    {
        $errors = [];
        
        // Validate date relationships
        $dob = $mappedData['patient_dob']['value'] ?? null;
        $serviceDate = $mappedData['service_date']['value'] ?? null;
        
        if ($dob && $serviceDate) {
            $dobTime = strtotime($dob);
            $serviceTime = strtotime($serviceDate);
            
            if ($serviceTime && $dobTime && $serviceTime < $dobTime) {
                $errors['service_date'] = ['Service date cannot be before patient date of birth'];
            }
        }
        
        // Validate insurance relationships
        $hasSecondary = $mappedData['has_secondary_insurance']['value'] ?? false;
        $secondaryId = $mappedData['secondary_insurance_id']['value'] ?? null;
        
        if ($hasSecondary && empty($secondaryId)) {
            $errors['secondary_insurance_id'] = ['Secondary insurance ID is required when secondary insurance is indicated'];
        }
        
        // Validate wound measurements
        $length = $mappedData['wound_length']['value'] ?? null;
        $width = $mappedData['wound_width']['value'] ?? null;
        $depth = $mappedData['wound_depth']['value'] ?? null;
        
        if ($length !== null && $width !== null) {
            if (is_numeric($length) && is_numeric($width)) {
                if ($length <= 0 || $width <= 0) {
                    $errors['wound_measurements'] = ['Wound measurements must be greater than 0'];
                }
                
                if ($depth !== null && is_numeric($depth) && $depth < 0) {
                    $errors['wound_depth'] = ['Wound depth cannot be negative'];
                }
            }
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    protected function validateNPI(string $value): bool
    {
        $npi = preg_replace('/[^0-9]/', '', $value);
        
        if (strlen($npi) !== 10) {
            return false;
        }
        
        // Luhn algorithm for NPI
        $digits = str_split($npi);
        $checkDigit = array_pop($digits);
        
        $sum = 24; // NPI constant
        $alternate = true;
        
        foreach (array_reverse($digits) as $digit) {
            $digit = (int) $digit;
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }
            $sum += $digit;
            $alternate = !$alternate;
        }
        
        $calculatedCheck = (10 - ($sum % 10)) % 10;
        
        return $calculatedCheck == $checkDigit;
    }

    protected function validateMedicareId(string $value): bool
    {
        // Medicare Beneficiary Identifier (MBI) format validation
        // 11 characters: 1-1-1-1-1-1-1-1-1-1-1
        // Positions 2,5,8,9 are alpha, others alphanumeric
        
        $mbi = preg_replace('/[^A-Z0-9]/i', '', strtoupper($value));
        
        if (strlen($mbi) !== 11) {
            return false;
        }
        
        // Check position requirements
        $alphaPositions = [1, 4, 7, 8]; // 0-indexed
        $excludedLetters = ['S', 'L', 'O', 'I', 'B', 'Z'];
        
        for ($i = 0; $i < strlen($mbi); $i++) {
            $char = $mbi[$i];
            
            if (in_array($i, $alphaPositions)) {
                if (!ctype_alpha($char) || in_array($char, $excludedLetters)) {
                    return false;
                }
            } else {
                if (!ctype_alnum($char) || in_array($char, $excludedLetters)) {
                    return false;
                }
            }
        }
        
        return true;
    }

    protected function getErrorMessage(string $fieldName, string $rule, array $params = []): string
    {
        $fieldLabel = ucwords(str_replace('_', ' ', $fieldName));
        
        $messages = [
            'required' => "{$fieldLabel} is required",
            'numeric' => "{$fieldLabel} must be numeric",
            'alpha' => "{$fieldLabel} must contain only letters",
            'alpha_spaces' => "{$fieldLabel} must contain only letters and spaces",
            'alphanumeric' => "{$fieldLabel} must contain only letters and numbers",
            'digits' => "{$fieldLabel} must be exactly {$params[0]} digits",
            'min' => "{$fieldLabel} must be at least {$params[0]} characters",
            'max' => "{$fieldLabel} must not exceed {$params[0]} characters",
            'date' => "{$fieldLabel} must be a valid date",
            'before' => "{$fieldLabel} must be before {$params[0]}",
            'after' => "{$fieldLabel} must be after {$params[0]}",
            'email' => "{$fieldLabel} must be a valid email address",
            'phone_us' => "{$fieldLabel} must be a valid US phone number",
            'npi' => "{$fieldLabel} must be a valid NPI number",
            'medicare_id' => "{$fieldLabel} must be a valid Medicare Beneficiary Identifier",
            'regex' => "{$fieldLabel} format is invalid",
        ];
        
        return $messages[$rule] ?? "{$fieldLabel} is invalid";
    }

    public function getValidationReport(array $results): string
    {
        $report = [];
        
        if ($results['valid']) {
            $report[] = "✓ All fields validated successfully";
        } else {
            $report[] = "✗ Validation failed";
            
            if (!empty($results['missing_required'])) {
                $report[] = "\nMissing Required Fields:";
                foreach ($results['missing_required'] as $field) {
                    $report[] = "  - " . ucwords(str_replace('_', ' ', $field));
                }
            }
            
            if (!empty($results['errors'])) {
                $report[] = "\nValidation Errors:";
                foreach ($results['errors'] as $field => $errors) {
                    $report[] = "  " . ucwords(str_replace('_', ' ', $field)) . ":";
                    foreach ($errors as $error) {
                        $report[] = "    - " . $error;
                    }
                }
            }
        }
        
        if (!empty($results['warnings'])) {
            $report[] = "\nWarnings:";
            foreach ($results['warnings'] as $field => $warning) {
                $report[] = "  - " . ucwords(str_replace('_', ' ', $field)) . ": " . $warning;
            }
        }
        
        return implode("\n", $report);
    }
}
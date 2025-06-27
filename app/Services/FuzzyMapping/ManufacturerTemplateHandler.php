<?php

namespace App\Services\FuzzyMapping;

use App\Models\Order\Manufacturer;
use App\Models\IVRTemplateField;
use Illuminate\Support\Facades\Log;

class ManufacturerTemplateHandler
{
    protected bool $testMode = false;
    
    protected array $manufacturerRules = [
        'Advanced Solution' => [
            'field_variations' => [
                'patient_first_name' => ['patient_fname', 'pt_first_name', 'patient_first'],
                'patient_last_name' => ['patient_lname', 'pt_last_name', 'patient_last'],
                'wound_type' => ['wound_description', 'wound_category', 'ulcer_type'],
                'wound_location' => ['anatomical_site', 'body_location', 'wound_site'],
            ],
            'date_format' => 'MM/DD/YYYY',
            'phone_format' => '(###) ###-####',
            'required_sections' => ['patient_information', 'clinical_information', 'insurance'],
        ],
        
        'Bio Excellence' => [
            'field_variations' => [
                'provider_npi' => ['physician_npi', 'npi_number', 'provider_npi_number'],
                'facility_name' => ['clinic_name', 'practice_name', 'facility'],
                'diagnosis_code' => ['icd10_code', 'diagnosis', 'dx_code'],
            ],
            'date_format' => 'MM/DD/YYYY',
            'phone_format' => '###-###-####',
            'required_sections' => ['provider_info', 'diagnosis', 'product_selection'],
        ],
        
        'Centurion Therapeutics' => [
            'field_variations' => [
                'insurance_id' => ['member_id', 'policy_number', 'ins_id'],
                'insurance_name' => ['carrier_name', 'payor_name', 'insurance_company'],
                'group_number' => ['group_id', 'group_no', 'insurance_group'],
            ],
            'date_format' => 'YYYY-MM-DD',
            'phone_format' => '###.###.####',
            'required_sections' => ['demographics', 'insurance_verification', 'authorization'],
        ],
        
        'ACZ Distribution' => [
            'field_variations' => [
                'wound_length' => ['length_cm', 'wound_size_length', 'l_cm'],
                'wound_width' => ['width_cm', 'wound_size_width', 'w_cm'],
                'wound_depth' => ['depth_cm', 'wound_size_depth', 'd_cm'],
            ],
            'date_format' => 'MM/DD/YY',
            'phone_format' => '(###) ###-####',
            'required_sections' => ['patient_data', 'wound_assessment', 'treatment_plan'],
        ],
        
        'Medlife Solutions' => [
            'field_variations' => [
                'provider_name' => ['physician_name', 'doctor_name', 'prescriber_name'],
                'provider_phone' => ['physician_phone', 'office_phone', 'contact_phone'],
                'facility_address' => ['clinic_address', 'practice_address', 'office_address'],
            ],
            'date_format' => 'M/D/YYYY',
            'phone_format' => '###-###-####',
            'required_sections' => ['prescriber_information', 'patient_demographics', 'clinical_notes'],
        ],
        
        'Biowound' => [
            'field_variations' => [
                'medicare_number' => ['medicare_id', 'hicn', 'medicare_beneficiary_id'],
                'secondary_insurance' => ['secondary_ins', 'supplemental_insurance', 'secondary_payor'],
                'referral_source' => ['referring_provider', 'referral_from', 'referred_by'],
            ],
            'date_format' => 'MM-DD-YYYY',
            'phone_format' => '### ### ####',
            'required_sections' => ['beneficiary_info', 'coverage_details', 'medical_necessity'],
        ],
    ];

    protected array $fieldTypeRules = [
        'date' => ['validate' => 'date', 'transform' => 'date_format'],
        'phone' => ['validate' => 'phone', 'transform' => 'phone_format'],
        'ssn' => ['validate' => 'ssn', 'transform' => 'ssn_format'],
        'npi' => ['validate' => 'npi', 'transform' => 'numeric'],
        'email' => ['validate' => 'email', 'transform' => 'lowercase'],
        'zip' => ['validate' => 'zip', 'transform' => 'zip_format'],
    ];

    public function __construct()
    {
        $this->testMode = config('fuzzy_mapping.test_mode', false);
    }

    public function getManufacturerRules(string $manufacturerName): array
    {
        return $this->manufacturerRules[$manufacturerName] ?? [];
    }

    public function getFieldVariations(string $manufacturerName, string $fieldName): array
    {
        $rules = $this->getManufacturerRules($manufacturerName);
        return $rules['field_variations'][$fieldName] ?? [];
    }

    public function applyManufacturerSpecificTransformations(
        string $manufacturerName,
        string $fieldName,
        $value
    ) {
        $rules = $this->getManufacturerRules($manufacturerName);
        
        // Apply date formatting
        if ($this->isDateField($fieldName) && isset($rules['date_format'])) {
            return $this->formatDate($value, $rules['date_format']);
        }
        
        // Apply phone formatting
        if ($this->isPhoneField($fieldName) && isset($rules['phone_format'])) {
            return $this->formatPhone($value, $rules['phone_format']);
        }
        
        // Apply field type specific transformations
        $fieldType = $this->detectFieldType($fieldName);
        if ($fieldType && isset($this->fieldTypeRules[$fieldType]['transform'])) {
            $transformation = $this->fieldTypeRules[$fieldType]['transform'];
            return $this->applyTransformation($value, $transformation);
        }
        
        return $value;
    }

    public function validateFieldValue(
        string $manufacturerName,
        string $fieldName,
        $value
    ): array {
        $errors = [];
        
        // Get template field definition
        $manufacturer = Manufacturer::where('name', $manufacturerName)->first();
        if (!$manufacturer) {
            return ['errors' => ['Manufacturer not found']];
        }
        
        $templateField = IVRTemplateField::where('manufacturer_id', $manufacturer->id)
            ->where('field_name', $fieldName)
            ->first();
        
        if ($templateField) {
            // Check required (skip in test mode)
            if (!$this->testMode && $templateField->is_required && empty($value)) {
                $errors[] = "Field '{$fieldName}' is required";
            }
            
            // Apply validation rules
            if ($templateField->validation_rules) {
                foreach ($templateField->validation_rules as $rule) {
                    if (!$this->validateRule($value, $rule)) {
                        $message = isset($rule['message']) ? $rule['message'] : 'Invalid value';
                        $errors[] = "Field '{$fieldName}' failed validation: {$message}";
                    }
                }
            }
        }
        
        // Apply field type validation (skip in test mode)
        if (!$this->testMode) {
            $fieldType = $this->detectFieldType($fieldName);
            if ($fieldType && isset($this->fieldTypeRules[$fieldType]['validate'])) {
                $validation = $this->fieldTypeRules[$fieldType]['validate'];
                if (!$this->validateFieldType($value, $validation)) {
                    $errors[] = "Field '{$fieldName}' has invalid format for type '{$fieldType}'";
                }
            }
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function getRequiredFields(string $manufacturerName, string $templateName): array
    {
        $manufacturer = Manufacturer::where('name', $manufacturerName)->first();
        if (!$manufacturer) {
            return [];
        }
        
        return IVRTemplateField::forTemplate($manufacturer->id, $templateName)
            ->required()
            ->pluck('field_name')
            ->toArray();
    }

    public function enrichFieldWithMetadata(
        string $manufacturerName,
        string $fieldName,
        array $fieldData
    ): array {
        $manufacturer = Manufacturer::where('name', $manufacturerName)->first();
        if (!$manufacturer) {
            return $fieldData;
        }
        
        $templateField = IVRTemplateField::where('manufacturer_id', $manufacturer->id)
            ->where('field_name', $fieldName)
            ->first();
        
        if ($templateField) {
            $fieldData['field_type'] = $templateField->field_type;
            $fieldData['is_required'] = $templateField->is_required;
            $fieldData['section'] = $templateField->section;
            $fieldData['description'] = $templateField->description;
            
            if ($templateField->field_metadata) {
                $fieldData['metadata'] = $templateField->field_metadata;
            }
        }
        
        return $fieldData;
    }

    protected function isDateField(string $fieldName): bool
    {
        $datePatterns = [
            '/date/i',
            '/dob/i',
            '/_at$/i',
            '/birth/i',
            '/expir/i',
        ];
        
        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return true;
            }
        }
        
        return false;
    }

    protected function isPhoneField(string $fieldName): bool
    {
        $phonePatterns = [
            '/phone/i',
            '/mobile/i',
            '/cell/i',
            '/tel/i',
            '/fax/i',
        ];
        
        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return true;
            }
        }
        
        return false;
    }

    protected function detectFieldType(string $fieldName): ?string
    {
        $typePatterns = [
            'date' => ['/date/i', '/dob/i', '/_at$/i'],
            'phone' => ['/phone/i', '/mobile/i', '/tel/i'],
            'email' => ['/email/i', '/e_mail/i'],
            'ssn' => ['/ssn/i', '/social_security/i'],
            'npi' => ['/npi/i'],
            'zip' => ['/zip/i', '/postal/i'],
        ];
        
        foreach ($typePatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $fieldName)) {
                    return $type;
                }
            }
        }
        
        return null;
    }

    protected function formatDate($value, string $format): string
    {
        try {
            // Handle array values
            if (is_array($value)) {
                $value = $value[0] ?? '';
            }
            
            // Convert to string if necessary
            $value = (string) $value;
            
            if (empty($value)) {
                return '';
            }
            
            $date = new \DateTime($value);
            
            // Convert format string to PHP format
            $phpFormat = str_replace(
                ['YYYY', 'YY', 'MM', 'M', 'DD', 'D'],
                ['Y', 'y', 'm', 'n', 'd', 'j'],
                $format
            );
            
            return $date->format($phpFormat);
        } catch (\Exception $e) {
            Log::warning('Failed to format date', [
                'value' => $value,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);
            return $value;
        }
    }

    protected function formatPhone($value, string $format): string
    {
        // Handle array values
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }
        
        // Convert to string if necessary
        $value = (string) $value;
        
        if (empty($value)) {
            return '';
        }
        
        // Remove all non-numeric characters
        $numeric = preg_replace('/[^0-9]/', '', $value);
        
        if (strlen($numeric) !== 10) {
            return $value; // Return original if not 10 digits
        }
        
        // Apply format
        $formatted = $format;
        $formatted = str_replace('###', substr($numeric, 0, 3), $formatted);
        $formatted = preg_replace('/###/', substr($numeric, 3, 3), $formatted, 1);
        $formatted = preg_replace('/####/', substr($numeric, 6, 4), $formatted, 1);
        
        return $formatted;
    }

    protected function applyTransformation($value, string $transformation): mixed
    {
        switch ($transformation) {
            case 'uppercase':
                return strtoupper($value);
            case 'lowercase':
                return strtolower($value);
            case 'numeric':
                return preg_replace('/[^0-9]/', '', $value);
            case 'ssn_format':
                $numeric = preg_replace('/[^0-9]/', '', $value);
                if (strlen($numeric) === 9) {
                    return substr($numeric, 0, 3) . '-' . substr($numeric, 3, 2) . '-' . substr($numeric, 5, 4);
                }
                return $value;
            case 'zip_format':
                $numeric = preg_replace('/[^0-9]/', '', $value);
                if (strlen($numeric) === 9) {
                    return substr($numeric, 0, 5) . '-' . substr($numeric, 5, 4);
                }
                return $numeric;
            default:
                return $value;
        }
    }

    protected function validateRule($value, array $rule): bool
    {
        switch ($rule['type'] ?? '') {
            case 'regex':
                return preg_match($rule['pattern'], $value);
            case 'min_length':
                return strlen($value) >= ($rule['value'] ?? 0);
            case 'max_length':
                return strlen($value) <= ($rule['value'] ?? PHP_INT_MAX);
            case 'numeric':
                return is_numeric($value);
            case 'alpha':
                return ctype_alpha($value);
            case 'alphanumeric':
                return ctype_alnum($value);
            default:
                return true;
        }
    }

    protected function validateFieldType($value, string $type): bool
    {
        switch ($type) {
            case 'date':
                return strtotime($value) !== false;
            case 'phone':
                $numeric = preg_replace('/[^0-9]/', '', $value);
                return strlen($numeric) === 10 || strlen($numeric) === 11;
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'ssn':
                $numeric = preg_replace('/[^0-9]/', '', $value);
                return strlen($numeric) === 9;
            case 'npi':
                $numeric = preg_replace('/[^0-9]/', '', $value);
                return strlen($numeric) === 10 && $this->validateNPI($numeric);
            case 'zip':
                $numeric = preg_replace('/[^0-9]/', '', $value);
                return strlen($numeric) === 5 || strlen($numeric) === 9;
            default:
                return true;
        }
    }

    protected function validateNPI(string $npi): bool
    {
        if (strlen($npi) !== 10) {
            return false;
        }
        
        // Luhn algorithm for NPI validation
        $digits = str_split($npi);
        $checkDigit = array_pop($digits);
        
        $sum = 24; // Start with 24 per NPI algorithm
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
}
<?php

namespace App\Services\FuzzyMapping;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FallbackStrategy
{
    /**
     * Apply fallback strategies for unmapped fields
     */
    public function applyFallbacks(
        array $mappedData,
        array $unmappedFields,
        string $manufacturerName
    ): array {
        foreach ($unmappedFields as $field) {
            // Skip if already mapped by other means
            if (isset($mappedData[$field])) {
                continue;
            }
            
            // Try various fallback strategies
            $fallbackValue = $this->attemptFallback($field, $mappedData, $manufacturerName);
            
            if ($fallbackValue !== null) {
                $mappedData[$field] = $fallbackValue;
            }
        }
        
        return $mappedData;
    }
    
    /**
     * Handle fields that cannot be mapped
     */
    public function handleUnmappableField(string $field, string $manufacturerName): array
    {
        Log::warning("Unmappable field encountered", [
            'field' => $field,
            'manufacturer' => $manufacturerName
        ]);
        
        // Return a standard unmappable response
        return [
            'value' => $this->getDefaultValue($field),
            'strategy' => 'unmappable',
            'confidence' => 0,
            'fhir_path' => '',
            'metadata' => [
                'reason' => 'No suitable mapping found',
                'attempted_strategies' => ['fuzzy_match', 'pattern_match', 'fallback', 'default'],
                'suggestions' => $this->suggestDataSource($field)
            ]
        ];
    }
    
    /**
     * Suggest possible data sources for unmapped field
     */
    public function suggestDataSource(string $fieldName): array
    {
        $suggestions = [];
        $lowerFieldName = strtolower($fieldName);
        
        // Patient-related fields
        if (Str::contains($lowerFieldName, ['patient', 'member', 'subscriber', 'beneficiary'])) {
            if (Str::contains($lowerFieldName, ['name', 'firstname', 'lastname', 'fullname'])) {
                $suggestions[] = 'FHIR Patient.name';
                $suggestions[] = 'Order patient data';
            }
            if (Str::contains($lowerFieldName, ['dob', 'birth', 'birthday'])) {
                $suggestions[] = 'FHIR Patient.birthDate';
            }
            if (Str::contains($lowerFieldName, ['phone', 'tel', 'mobile'])) {
                $suggestions[] = 'FHIR Patient.telecom[system=phone]';
            }
            if (Str::contains($lowerFieldName, ['address', 'street', 'city', 'state', 'zip'])) {
                $suggestions[] = 'FHIR Patient.address';
            }
            if (Str::contains($lowerFieldName, ['gender', 'sex'])) {
                $suggestions[] = 'FHIR Patient.gender';
            }
        }
        
        // Insurance-related fields
        if (Str::contains($lowerFieldName, ['insurance', 'payer', 'carrier', 'plan'])) {
            if (Str::contains($lowerFieldName, ['name', 'company'])) {
                $suggestions[] = 'FHIR Coverage.payor';
                $suggestions[] = 'Insurance verification data';
            }
            if (Str::contains($lowerFieldName, ['member', 'subscriber', 'policy', 'id', 'number'])) {
                $suggestions[] = 'FHIR Coverage.identifier';
                $suggestions[] = 'Insurance card OCR data';
            }
            if (Str::contains($lowerFieldName, ['group'])) {
                $suggestions[] = 'FHIR Coverage.class[type=group]';
            }
        }
        
        // Provider-related fields
        if (Str::contains($lowerFieldName, ['provider', 'physician', 'doctor', 'practitioner', 'npi'])) {
            $suggestions[] = 'FHIR Practitioner resource';
            $suggestions[] = 'Order provider data';
        }
        
        // Diagnosis-related fields
        if (Str::contains($lowerFieldName, ['diagnosis', 'icd', 'condition', 'dx'])) {
            $suggestions[] = 'FHIR Condition.code';
            $suggestions[] = 'Order diagnosis data';
        }
        
        // Date-related fields
        if (Str::contains($lowerFieldName, ['date', 'effective', 'start', 'end', 'service'])) {
            if (Str::contains($lowerFieldName, ['service', 'treatment'])) {
                $suggestions[] = 'Order service date';
            }
            if (Str::contains($lowerFieldName, ['effective', 'coverage'])) {
                $suggestions[] = 'FHIR Coverage.period';
            }
        }
        
        // Facility-related fields
        if (Str::contains($lowerFieldName, ['facility', 'location', 'place', 'pos'])) {
            $suggestions[] = 'Order facility data';
            $suggestions[] = 'FHIR Location resource';
        }
        
        // Add generic suggestions if no specific matches
        if (empty($suggestions)) {
            $suggestions[] = 'Manual entry required';
            $suggestions[] = 'Check order additional data';
            $suggestions[] = 'Review FHIR resources';
        }
        
        return $suggestions;
    }
    
    /**
     * Attempt various fallback strategies
     */
    protected function attemptFallback(string $field, array $mappedData, string $manufacturerName): ?array
    {
        // Try derived value strategy
        $derivedValue = $this->tryDerivedValue($field, $mappedData);
        if ($derivedValue !== null) {
            return [
                'value' => $derivedValue,
                'strategy' => 'derived',
                'confidence' => 0.7,
                'fhir_path' => '',
                'metadata' => [
                    'derived_from' => 'Multiple fields'
                ]
            ];
        }
        
        // Try manufacturer-specific defaults
        $defaultValue = $this->getManufacturerDefault($field, $manufacturerName);
        if ($defaultValue !== null) {
            return [
                'value' => $defaultValue,
                'strategy' => 'default',
                'confidence' => 0.5,
                'fhir_path' => '',
                'metadata' => [
                    'default_type' => 'manufacturer_specific'
                ]
            ];
        }
        
        // Try conditional defaults
        $conditionalDefault = $this->getConditionalDefault($field, $mappedData);
        if ($conditionalDefault !== null) {
            return [
                'value' => $conditionalDefault,
                'strategy' => 'conditional_default',
                'confidence' => 0.6,
                'fhir_path' => '',
                'metadata' => [
                    'condition' => 'Based on other field values'
                ]
            ];
        }
        
        return null;
    }
    
    /**
     * Try to derive value from other mapped fields
     */
    protected function tryDerivedValue(string $field, array $mappedData): ?string
    {
        $lowerField = strtolower($field);
        
        // Derive full name from first and last name
        if (Str::contains($lowerField, ['fullname', 'patient_name', 'member_name'])) {
            $firstName = $this->findMappedValue($mappedData, ['first_name', 'firstname', 'given_name']);
            $lastName = $this->findMappedValue($mappedData, ['last_name', 'lastname', 'family_name']);
            
            if ($firstName && $lastName) {
                return trim("$firstName $lastName");
            }
        }
        
        // Derive full address from components
        if (Str::contains($lowerField, ['full_address', 'complete_address'])) {
            $street = $this->findMappedValue($mappedData, ['street', 'address1', 'address_line1']);
            $city = $this->findMappedValue($mappedData, ['city']);
            $state = $this->findMappedValue($mappedData, ['state', 'province']);
            $zip = $this->findMappedValue($mappedData, ['zip', 'postal', 'zipcode']);
            
            if ($street && $city && $state) {
                return trim("$street, $city, $state" . ($zip ? " $zip" : ""));
            }
        }
        
        // Derive age from date of birth
        if (Str::contains($lowerField, ['age', 'patient_age'])) {
            $dob = $this->findMappedValue($mappedData, ['dob', 'birth_date', 'date_of_birth']);
            if ($dob) {
                try {
                    $birthDate = new \DateTime($dob);
                    $now = new \DateTime();
                    $age = $now->diff($birthDate)->y;
                    return (string) $age;
                } catch (\Exception $e) {
                    // Invalid date format
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get manufacturer-specific default values
     */
    protected function getManufacturerDefault(string $field, string $manufacturerName): ?string
    {
        $defaults = [
            'convatec' => [
                'referral_type' => 'Standard',
                'urgency' => 'Routine',
                'authorization_required' => 'Yes'
            ],
            'mölnlycke' => [
                'product_category' => 'Advanced Wound Care',
                'frequency' => 'As directed',
                'duration' => '90 days'
            ],
            'hollister' => [
                'service_type' => 'DME',
                'place_of_service' => 'Home',
                'modifier' => 'KX'
            ]
        ];
        
        $manufacturerLower = strtolower($manufacturerName);
        if (isset($defaults[$manufacturerLower])) {
            $fieldLower = strtolower($field);
            foreach ($defaults[$manufacturerLower] as $defaultField => $defaultValue) {
                if (Str::contains($fieldLower, strtolower($defaultField))) {
                    return $defaultValue;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get conditional default based on other field values
     */
    protected function getConditionalDefault(string $field, array $mappedData): ?string
    {
        $lowerField = strtolower($field);
        
        // Default country based on state
        if (Str::contains($lowerField, ['country'])) {
            $state = $this->findMappedValue($mappedData, ['state', 'province']);
            if ($state && $this->isUSState($state)) {
                return 'USA';
            }
        }
        
        // Default phone country code
        if (Str::contains($lowerField, ['country_code', 'phone_country'])) {
            $phone = $this->findMappedValue($mappedData, ['phone', 'telephone', 'mobile']);
            if ($phone && !Str::startsWith($phone, '+')) {
                return '+1'; // Default to US
            }
        }
        
        // Default relationship to self if patient and subscriber names match
        if (Str::contains($lowerField, ['relationship', 'subscriber_relationship'])) {
            $patientName = $this->findMappedValue($mappedData, ['patient_name', 'member_name']);
            $subscriberName = $this->findMappedValue($mappedData, ['subscriber_name', 'policy_holder_name']);
            
            if ($patientName && $subscriberName && 
                strtolower($patientName) === strtolower($subscriberName)) {
                return 'Self';
            }
        }
        
        return null;
    }
    
    /**
     * Get default value for unmappable field
     */
    protected function getDefaultValue(string $field): string
    {
        $lowerField = strtolower($field);
        
        // Boolean fields
        if (Str::contains($lowerField, ['is_', 'has_', 'authorization_required', 'active', 'primary'])) {
            return 'No';
        }
        
        // Date fields - use today's date
        if (Str::contains($lowerField, ['date', 'effective']) && 
            !Str::contains($lowerField, ['birth', 'dob'])) {
            return date('Y-m-d');
        }
        
        // Relationship fields
        if (Str::contains($lowerField, ['relationship'])) {
            return 'Self';
        }
        
        // Status fields
        if (Str::contains($lowerField, ['status'])) {
            return 'Active';
        }
        
        // Type fields
        if (Str::contains($lowerField, ['type'])) {
            return 'Standard';
        }
        
        // Return empty string as last resort
        return '';
    }
    
    /**
     * Find mapped value by field name patterns
     */
    protected function findMappedValue(array $mappedData, array $patterns): ?string
    {
        foreach ($mappedData as $fieldName => $data) {
            $lowerFieldName = strtolower($fieldName);
            foreach ($patterns as $pattern) {
                if (Str::contains($lowerFieldName, strtolower($pattern))) {
                    return $data['value'] ?? null;
                }
            }
        }
        return null;
    }
    
    /**
     * Check if string is a US state
     */
    protected function isUSState(string $state): bool
    {
        $states = [
            'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
            'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
            'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
            'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
            'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
        ];
        
        return in_array(strtoupper($state), $states);
    }
}
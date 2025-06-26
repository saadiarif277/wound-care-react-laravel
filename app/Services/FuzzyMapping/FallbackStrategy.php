<?php

namespace App\Services\FuzzyMapping;

use Illuminate\Support\Facades\Log;

class FallbackStrategy
{
    protected array $defaultValues = [
        // Patient defaults
        'patient_title' => 'Mr./Ms.',
        'patient_suffix' => '',
        'patient_middle_name' => '',
        
        // Provider defaults
        'provider_title' => 'Dr.',
        'provider_suffix' => 'MD',
        'provider_fax' => '',
        
        // Facility defaults
        'facility_type' => 'Clinic',
        'facility_fax' => '',
        
        // Insurance defaults
        'insurance_group_number' => 'N/A',
        'insurance_plan_type' => 'PPO',
        'prior_auth_required' => 'No',
        
        // Clinical defaults
        'wound_drainage' => 'None',
        'wound_odor' => 'None',
        'periwound_condition' => 'Intact',
        'pain_level' => '0',
        'allergies' => 'NKDA',
        
        // Administrative defaults
        'urgency' => 'Routine',
        'delivery_method' => 'Standard',
        'special_instructions' => 'None',
    ];

    protected array $derivedRules = [
        'patient_full_name' => [
            'from' => ['patient_first_name', 'patient_last_name'],
            'format' => '{patient_first_name} {patient_last_name}',
        ],
        'patient_age' => [
            'from' => ['patient_dob'],
            'calculate' => 'age_from_dob',
        ],
        'provider_full_name' => [
            'from' => ['provider_first_name', 'provider_last_name', 'provider_title'],
            'format' => '{provider_title} {provider_first_name} {provider_last_name}',
        ],
        'facility_full_address' => [
            'from' => ['facility_address_line1', 'facility_city', 'facility_state', 'facility_zip'],
            'format' => '{facility_address_line1}, {facility_city}, {facility_state} {facility_zip}',
        ],
        'wound_size' => [
            'from' => ['wound_length', 'wound_width', 'wound_depth'],
            'format' => '{wound_length}cm x {wound_width}cm x {wound_depth}cm',
        ],
        'insurance_effective_date' => [
            'from' => [],
            'default' => 'current_date',
        ],
    ];

    protected array $conditionalDefaults = [
        'secondary_insurance_name' => [
            'condition' => ['has_secondary_insurance' => false],
            'value' => 'None',
        ],
        'wound_depth' => [
            'condition' => ['wound_stage' => ['1', '2']],
            'value' => '0',
        ],
        'prior_auth_number' => [
            'condition' => ['prior_auth_required' => 'No'],
            'value' => 'N/A',
        ],
    ];

    public function applyFallbacks(
        array $mappedData,
        array $missingFields,
        string $manufacturerName
    ): array {
        $enhanced = $mappedData;
        
        // Apply default values for missing fields
        foreach ($missingFields as $field) {
            if (isset($this->defaultValues[$field])) {
                $enhanced[$field] = [
                    'value' => $this->defaultValues[$field],
                    'confidence' => 1.0,
                    'strategy' => 'default',
                    'fhir_path' => null,
                ];
                
                Log::info("Applied default value for {$field}", [
                    'field' => $field,
                    'value' => $this->defaultValues[$field],
                    'manufacturer' => $manufacturerName,
                ]);
            }
        }
        
        // Apply derived values
        foreach ($this->derivedRules as $targetField => $rule) {
            if (!isset($enhanced[$targetField])) {
                $derivedValue = $this->deriveValue($rule, $enhanced);
                if ($derivedValue !== null) {
                    $enhanced[$targetField] = [
                        'value' => $derivedValue,
                        'confidence' => 0.9,
                        'strategy' => 'derived',
                        'fhir_path' => null,
                    ];
                    
                    Log::info("Derived value for {$targetField}", [
                        'field' => $targetField,
                        'value' => $derivedValue,
                        'manufacturer' => $manufacturerName,
                    ]);
                }
            }
        }
        
        // Apply conditional defaults
        foreach ($this->conditionalDefaults as $field => $conditional) {
            if (!isset($enhanced[$field])) {
                if ($this->checkCondition($conditional['condition'], $enhanced)) {
                    $enhanced[$field] = [
                        'value' => $conditional['value'],
                        'confidence' => 0.95,
                        'strategy' => 'conditional_default',
                        'fhir_path' => null,
                    ];
                    
                    Log::info("Applied conditional default for {$field}", [
                        'field' => $field,
                        'value' => $conditional['value'],
                        'condition' => $conditional['condition'],
                        'manufacturer' => $manufacturerName,
                    ]);
                }
            }
        }
        
        // Apply manufacturer-specific fallbacks
        $enhanced = $this->applyManufacturerFallbacks($enhanced, $manufacturerName);
        
        return $enhanced;
    }

    protected function deriveValue(array $rule, array $data): ?string
    {
        // Check if all required fields are present
        foreach ($rule['from'] as $requiredField) {
            if (!isset($data[$requiredField]['value'])) {
                return null;
            }
        }
        
        // Handle special calculations
        if (isset($rule['calculate'])) {
            switch ($rule['calculate']) {
                case 'age_from_dob':
                    $dob = $data['patient_dob']['value'] ?? null;
                    if ($dob) {
                        $birthDate = new \DateTime($dob);
                        $now = new \DateTime();
                        $age = $now->diff($birthDate)->y;
                        return (string) $age;
                    }
                    break;
            }
        }
        
        // Handle special defaults
        if (isset($rule['default'])) {
            switch ($rule['default']) {
                case 'current_date':
                    return date('Y-m-d');
                case 'current_datetime':
                    return date('Y-m-d H:i:s');
            }
        }
        
        // Handle format-based derivation
        if (isset($rule['format'])) {
            $value = $rule['format'];
            foreach ($rule['from'] as $field) {
                $fieldValue = $data[$field]['value'] ?? '';
                $value = str_replace('{' . $field . '}', $fieldValue, $value);
            }
            
            // Clean up extra spaces
            $value = preg_replace('/\s+/', ' ', trim($value));
            
            return $value;
        }
        
        return null;
    }

    protected function checkCondition(array $condition, array $data): bool
    {
        foreach ($condition as $field => $expectedValue) {
            $actualValue = $data[$field]['value'] ?? null;
            
            if (is_array($expectedValue)) {
                if (!in_array($actualValue, $expectedValue)) {
                    return false;
                }
            } else {
                if ($actualValue != $expectedValue) {
                    return false;
                }
            }
        }
        
        return true;
    }

    protected function applyManufacturerFallbacks(array $data, string $manufacturerName): array
    {
        $manufacturerDefaults = [
            'Advanced Solution' => [
                'product_frequency' => 'Weekly',
                'product_duration' => '12 weeks',
                'application_method' => 'Topical',
            ],
            'Bio Excellence' => [
                'storage_requirements' => 'Room Temperature',
                'preparation_required' => 'No',
                'sterile' => 'Yes',
            ],
            'Centurion Therapeutics' => [
                'medicare_coverage' => 'Yes',
                'documentation_required' => 'Yes',
                'prior_auth_typical' => '3-5 days',
            ],
            'ACZ Distribution' => [
                'shipping_method' => 'Ground',
                'cold_chain_required' => 'No',
                'direct_to_patient' => 'Yes',
            ],
            'Medlife Solutions' => [
                'refill_frequency' => 'Monthly',
                'auto_refill' => 'No',
                'copay_assistance' => 'Available',
            ],
            'Biowound' => [
                'application_training' => 'Required',
                'nursing_assistance' => 'Recommended',
                'follow_up_required' => '2 weeks',
            ],
        ];
        
        if (isset($manufacturerDefaults[$manufacturerName])) {
            foreach ($manufacturerDefaults[$manufacturerName] as $field => $value) {
                if (!isset($data[$field])) {
                    $data[$field] = [
                        'value' => $value,
                        'confidence' => 0.95,
                        'strategy' => 'manufacturer_default',
                        'fhir_path' => null,
                    ];
                }
            }
        }
        
        return $data;
    }

    public function handleUnmappableField(
        string $fieldName,
        string $manufacturerName
    ): array {
        // Log the unmappable field for analysis
        Log::warning("Unmappable field encountered", [
            'field' => $fieldName,
            'manufacturer' => $manufacturerName,
            'timestamp' => now()->toIso8601String(),
        ]);
        
        // Check if it's a known optional field
        $optionalFields = [
            'patient_middle_initial',
            'provider_middle_name',
            'alternate_phone',
            'emergency_contact',
            'referring_provider',
            'secondary_diagnosis',
            'previous_treatments',
            'contraindications',
        ];
        
        if (in_array($fieldName, $optionalFields)) {
            return [
                'value' => '',
                'confidence' => 1.0,
                'strategy' => 'optional_empty',
                'fhir_path' => null,
            ];
        }
        
        // Check if it's a field that should be user-provided
        $userProvidedFields = [
            'signature',
            'consent_date',
            'witness_signature',
            'relationship_to_patient',
            'power_of_attorney',
        ];
        
        if (in_array($fieldName, $userProvidedFields)) {
            return [
                'value' => '[TO BE PROVIDED]',
                'confidence' => 1.0,
                'strategy' => 'user_required',
                'fhir_path' => null,
            ];
        }
        
        // Return a placeholder for truly unmappable fields
        return [
            'value' => '[UNMAPPED]',
            'confidence' => 0.0,
            'strategy' => 'unmappable',
            'fhir_path' => null,
            'requires_manual_entry' => true,
        ];
    }

    public function suggestDataSource(string $fieldName): ?string
    {
        $dataSources = [
            'patient_pcp' => 'Primary Care Physician information from patient\'s medical record',
            'insurance_group_number' => 'Insurance card or eligibility verification',
            'prior_auth_number' => 'Insurance pre-authorization response',
            'referring_provider_npi' => 'Referral form or order',
            'wound_etiology' => 'Medical diagnosis or assessment',
            'comorbidities' => 'Patient medical history',
            'current_medications' => 'Medication list from patient record',
            'allergies' => 'Allergy list from patient record',
            'last_debridement_date' => 'Procedure notes or treatment history',
            'culture_results' => 'Laboratory reports',
        ];
        
        return $dataSources[$fieldName] ?? null;
    }
}
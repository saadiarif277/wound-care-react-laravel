<?php

namespace App\Services\Templates;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UnifiedTemplateMappingEngine
{
    private array $mappingRules;
    private array $fieldTransformers;
    
    public function __construct()
    {
        $this->loadMappingRules();
        $this->registerFieldTransformers();
    }
    
    /**
     * Map insurance data from any source to any template format
     */
    public function mapInsuranceData(array $sourceData, string $targetTemplate): array
    {
        $mappingRules = $this->getMappingRulesForTemplate($targetTemplate);
        $mappedData = [];
        
        foreach ($mappingRules as $targetField => $mappingRule) {
            $value = $this->extractValueByRule($sourceData, $mappingRule);
            
            if ($value !== null) {
                $mappedData[$targetField] = $this->formatValue($value, $mappingRule);
            }
        }
        
        // Apply post-processing rules
        $mappedData = $this->applyPostProcessing($mappedData, $targetTemplate);
        
        return $mappedData;
    }
    
    /**
     * Load all mapping rules from config/database
     */
    private function loadMappingRules(): void
    {
        $this->mappingRules = [
            'docuseal_ivr' => [
                // Patient Information Mappings
                'patientInfo.patientName' => [
                    'source' => 'patient_first_name',
                    'fallbacks' => ['patient.first_name', 'patientFirstName'],
                    'transform' => 'fullName',
                    'combine_with' => 'patient_last_name'
                ],
                'patientInfo.dateOfBirth' => [
                    'source' => 'patient_dob',
                    'fallbacks' => ['patient.dob', 'patientDateOfBirth'],
                    'transform' => 'date'
                ],
                'patientInfo.patientId' => [
                    'source' => 'patient_member_id',
                    'fallbacks' => ['member_id', 'subscriber_id', 'insurance_id']
                ],
                
                // Insurance Information Mappings
                'insuranceInfo.primaryInsurance.primaryInsuranceName' => [
                    'source' => 'payer_name',
                    'fallbacks' => ['insurance_name', 'primary_insurance'],
                    'transform' => 'insuranceName'
                ],
                'insuranceInfo.primaryInsurance.primaryMemberId' => [
                    'source' => 'patient_member_id',
                    'fallbacks' => ['member_id', 'policy_number']
                ],
                'insuranceInfo.primaryInsurance.groupNumber' => [
                    'source' => 'group_number',
                    'fallbacks' => ['insurance_group', 'group_id']
                ],
                'insuranceInfo.primaryInsurance.payerPhone' => [
                    'source' => 'payer_phone',
                    'fallbacks' => ['insurance_phone'],
                    'transform' => 'phone'
                ],
                
                // Provider Information Mappings
                'providerInfo.providerName' => [
                    'source' => 'provider_name',
                    'fallbacks' => ['provider.name', 'ordering_provider']
                ],
                'providerInfo.providerNPI' => [
                    'source' => 'provider_npi',
                    'fallbacks' => ['provider.npi', 'npi']
                ],
                
                // Facility Information Mappings  
                'facilityInfo.facilityName' => [
                    'source' => 'facility_name',
                    'fallbacks' => ['facility.name', 'location_name']
                ],
                'facilityInfo.facilityAddress' => [
                    'source' => 'facility_address',
                    'fallbacks' => ['facility.address'],
                    'transform' => 'address'
                ]
            ],
            
            'fhir_coverage' => [
                'subscriber.reference' => [
                    'source' => 'patient_fhir_id',
                    'prefix' => 'Patient/'
                ],
                'beneficiary.reference' => [
                    'source' => 'patient_fhir_id',
                    'prefix' => 'Patient/'
                ],
                'payor.0.display' => [
                    'source' => 'payer_name',
                    'fallbacks' => ['insurance_name']
                ],
                'identifier.0.value' => [
                    'source' => 'patient_member_id',
                    'fallbacks' => ['member_id']
                ],
                'class.0.value' => [
                    'source' => 'group_number',
                    'fallbacks' => ['plan_code']
                ]
            ],
            
            'quick_request' => [
                'patient_first_name' => [
                    'source' => 'patientInfo.firstName',
                    'fallbacks' => ['patient.first_name', 'firstName']
                ],
                'patient_last_name' => [
                    'source' => 'patientInfo.lastName', 
                    'fallbacks' => ['patient.last_name', 'lastName']
                ],
                'payer_name' => [
                    'source' => 'insuranceInfo.primaryInsurance.name',
                    'fallbacks' => ['insurance_name', 'primary_payer']
                ],
                'patient_member_id' => [
                    'source' => 'insuranceInfo.primaryInsurance.memberId',
                    'fallbacks' => ['member_id', 'subscriber_id']
                ]
            ]
        ];
    }
    
    /**
     * Register field transformation functions
     */
    private function registerFieldTransformers(): void
    {
        $this->fieldTransformers = [
            'fullName' => function($value, $rule, $sourceData) {
                $firstName = $value;
                $lastName = data_get($sourceData, str_replace('first_name', 'last_name', $rule['source']));
                return trim("$firstName $lastName");
            },
            
            'date' => function($value) {
                if (empty($value)) return null;
                try {
                    return \Carbon\Carbon::parse($value)->format('Y-m-d');
                } catch (\Exception $e) {
                    return $value;
                }
            },
            
            'phone' => function($value) {
                // Clean and format phone number
                $cleaned = preg_replace('/[^0-9]/', '', $value);
                if (strlen($cleaned) === 10) {
                    return sprintf('(%s) %s-%s', 
                        substr($cleaned, 0, 3),
                        substr($cleaned, 3, 3),
                        substr($cleaned, 6, 4)
                    );
                }
                return $value;
            },
            
            'insuranceName' => function($value) {
                // Standardize insurance names
                $standardNames = [
                    'BCBS' => 'Blue Cross Blue Shield',
                    'UHC' => 'United Healthcare',
                    'Medicare Part B' => 'Medicare',
                ];
                
                foreach ($standardNames as $short => $full) {
                    if (stripos($value, $short) !== false) {
                        return $full;
                    }
                }
                return $value;
            },
            
            'address' => function($value, $rule, $sourceData) {
                if (is_string($value)) return $value;
                
                // Build address from components
                $components = [
                    data_get($sourceData, 'facility_address_line1'),
                    data_get($sourceData, 'facility_address_line2'),
                    data_get($sourceData, 'facility_city'),
                    data_get($sourceData, 'facility_state'),
                    data_get($sourceData, 'facility_zip')
                ];
                
                return implode(', ', array_filter($components));
            }
        ];
    }
    
    /**
     * Extract value using dot notation or complex rules
     */
    private function extractValueByRule(array $data, $rule)
    {
        // Simple string path
        if (is_string($rule)) {
            return data_get($data, $rule);
        }
        
        // Complex rule with fallbacks and transformations
        if (is_array($rule)) {
            // Try primary source
            $value = data_get($data, $rule['source']);
            
            // Try fallback sources
            if ($value === null && isset($rule['fallbacks'])) {
                foreach ($rule['fallbacks'] as $fallback) {
                    $value = data_get($data, $fallback);
                    if ($value !== null) break;
                }
            }
            
            // Apply prefix if set
            if ($value !== null && isset($rule['prefix'])) {
                $value = $rule['prefix'] . $value;
            }
            
            // Apply transformation
            if ($value !== null && isset($rule['transform'])) {
                $value = $this->applyTransformation($value, $rule['transform'], $rule, $data);
            }
            
            return $value;
        }
        
        return null;
    }
    
    /**
     * Apply transformation to value
     */
    private function applyTransformation($value, string $transformer, array $rule, array $sourceData)
    {
        if (isset($this->fieldTransformers[$transformer])) {
            return call_user_func($this->fieldTransformers[$transformer], $value, $rule, $sourceData);
        }
        
        return $value;
    }
    
    /**
     * Format value based on rule specifications
     */
    private function formatValue($value, $rule): mixed
    {
        if (!is_array($rule)) {
            return $value;
        }
        
        // Apply formatting rules
        if (isset($rule['format'])) {
            switch ($rule['format']) {
                case 'uppercase':
                    return strtoupper($value);
                case 'lowercase':
                    return strtolower($value);
                case 'title':
                    return Str::title($value);
            }
        }
        
        return $value;
    }
    
    /**
     * Get mapping rules for specific template
     */
    private function getMappingRulesForTemplate(string $template): array
    {
        return $this->mappingRules[$template] ?? [];
    }
    
    /**
     * Apply post-processing rules to mapped data
     */
    private function applyPostProcessing(array $mappedData, string $template): array
    {
        // Template-specific post-processing
        switch ($template) {
            case 'docuseal_ivr':
                // Ensure required fields have defaults
                $mappedData['patientInfo']['consentToTreat'] = $mappedData['patientInfo']['consentToTreat'] ?? true;
                $mappedData['submissionDate'] = $mappedData['submissionDate'] ?? date('Y-m-d');
                break;
                
            case 'fhir_coverage':
                // Add FHIR metadata
                $mappedData['resourceType'] = 'Coverage';
                $mappedData['status'] = $mappedData['status'] ?? 'active';
                break;
        }
        
        return $mappedData;
    }
    
    /**
     * Calculate mapping completeness percentage
     */
    public function calculateMappingCompleteness(array $sourceData, string $targetTemplate): array
    {
        $mappingRules = $this->getMappingRulesForTemplate($targetTemplate);
        $totalFields = count($mappingRules);
        $mappedFields = 0;
        $missingFields = [];
        
        foreach ($mappingRules as $targetField => $rule) {
            $value = $this->extractValueByRule($sourceData, $rule);
            if ($value !== null) {
                $mappedFields++;
            } else {
                $missingFields[] = $targetField;
            }
        }
        
        return [
            'percentage' => $totalFields > 0 ? round(($mappedFields / $totalFields) * 100, 2) : 0,
            'mapped' => $mappedFields,
            'total' => $totalFields,
            'missing' => $missingFields
        ];
    }
}
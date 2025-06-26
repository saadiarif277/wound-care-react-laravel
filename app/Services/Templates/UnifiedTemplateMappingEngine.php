<?php

namespace App\Services\Templates;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Services\FuzzyMapping\IVRMappingOrchestrator;
use Illuminate\Support\Facades\Log;

class UnifiedTemplateMappingEngine
{
    private array $mappingRules;
    private array $fieldTransformers;
    private ?IVRMappingOrchestrator $fuzzyMapper = null;

    public function __construct()
    {
        $this->loadMappingRules();
        $this->registerFieldTransformers();
        
        // Inject fuzzy mapper if available
        try {
            $this->fuzzyMapper = app(IVRMappingOrchestrator::class);
        } catch (\Exception $e) {
            Log::warning('Fuzzy mapping service not available', ['error' => $e->getMessage()]);
        }
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
     * Map data using the fuzzy matching system for manufacturer IVR templates
     */
    public function mapWithFuzzyMatching(
        array $fhirData, 
        array $additionalData, 
        int $manufacturerId, 
        string $templateName = 'insurance-verification'
    ): array {
        if (!$this->fuzzyMapper) {
            Log::warning('Fuzzy mapper not available, falling back to standard mapping');
            return $this->mapInsuranceData(array_merge($fhirData, $additionalData), 'docuseal_ivr');
        }

        try {
            $result = $this->fuzzyMapper->mapDataForIVR($fhirData, $additionalData, $manufacturerId, $templateName);
            
            if ($result['success']) {
                return $result['mapped_fields'];
            } else {
                Log::warning('Fuzzy mapping failed', [
                    'manufacturer_id' => $manufacturerId,
                    'template' => $templateName,
                    'validation' => $result['validation'] ?? []
                ]);
                
                // Fall back to standard mapping if fuzzy matching fails
                return $this->mapInsuranceData(array_merge($fhirData, $additionalData), 'docuseal_ivr');
            }
        } catch (\Exception $e) {
            Log::error('Error in fuzzy mapping', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $manufacturerId
            ]);
            
            // Fall back to standard mapping on error
            return $this->mapInsuranceData(array_merge($fhirData, $additionalData), 'docuseal_ivr');
        }
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
                    'fallbacks' => ['patient.first_name', 'patientFirstName', 'patient_name'],
                    'transform' => 'fullName',
                    'combine_with' => 'patient_last_name'
                ],
                'patientInfo.dateOfBirth' => [
                    'source' => 'patient_dob',
                    'fallbacks' => ['patient.dob', 'patientDateOfBirth', 'dob'],
                    'transform' => 'date'
                ],
                'patientInfo.patientId' => [
                    'source' => 'patient_member_id',
                    'fallbacks' => ['member_id', 'subscriber_id', 'insurance_id']
                ],
                'patientInfo.gender' => [
                    'source' => 'patient_gender',
                    'fallbacks' => ['patient.gender', 'gender']
                ],
                'patientInfo.address' => [
                    'source' => 'patient_address',
                    'fallbacks' => ['patient.address', 'address']
                ],
                'patientInfo.city' => [
                    'source' => 'patient_city',
                    'fallbacks' => ['patient.city', 'city']
                ],
                'patientInfo.state' => [
                    'source' => 'patient_state',
                    'fallbacks' => ['patient.state', 'state']
                ],
                'patientInfo.zip' => [
                    'source' => 'patient_zip',
                    'fallbacks' => ['patient.zip', 'zip']
                ],
                'patientInfo.homePhone' => [
                    'source' => 'patient_home_phone',
                    'fallbacks' => ['patient.home_phone', 'home_phone'],
                    'transform' => 'phone'
                ],
                'patientInfo.mobile' => [
                    'source' => 'patient_mobile',
                    'fallbacks' => ['patient.mobile', 'mobile'],
                    'transform' => 'phone'
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
                ],
                'facilityInfo.facilityCity' => [
                    'source' => 'facility_city',
                    'fallbacks' => ['facility.city']
                ],
                'facilityInfo.facilityState' => [
                    'source' => 'facility_state',
                    'fallbacks' => ['facility.state']
                ],
                'facilityInfo.facilityZip' => [
                    'source' => 'facility_zip',
                    'fallbacks' => ['facility.zip']
                ],
                'facilityInfo.facilityNpi' => [
                    'source' => 'facility_npi',
                    'fallbacks' => ['facility.npi']
                ],
                
                // Wound Information Mappings
                'woundInfo.woundType' => [
                    'source' => 'wound_type',
                    'fallbacks' => ['woundType', 'diagnosis_type']
                ],
                'woundInfo.woundLocation' => [
                    'source' => 'wound_location',
                    'fallbacks' => ['woundLocation', 'anatomical_location']
                ],
                'woundInfo.woundSize' => [
                    'source' => 'wound_size',
                    'fallbacks' => ['woundSize', 'size']
                ],
                'woundInfo.primaryDiagnosis' => [
                    'source' => 'primary_diagnosis',
                    'fallbacks' => ['primaryDiagnosis', 'diagnosis1']
                ],
                'woundInfo.secondaryDiagnosis' => [
                    'source' => 'secondary_diagnosis',
                    'fallbacks' => ['secondaryDiagnosis', 'diagnosis2']
                ],
                'woundInfo.tertiaryDiagnosis' => [
                    'source' => 'tertiary_diagnosis',
                    'fallbacks' => ['tertiaryDiagnosis', 'diagnosis3']
                ],
                'woundInfo.knownConditions' => [
                    'source' => 'known_conditions',
                    'fallbacks' => ['knownConditions', 'comorbidities']
                ],
                
                // Provider Information Extended
                'providerInfo.ptan' => [
                    'source' => 'provider_ptan',
                    'fallbacks' => ['provider.ptan', 'ptan']
                ],
                'providerInfo.taxId' => [
                    'source' => 'provider_tax_id',
                    'fallbacks' => ['provider.tax_id', 'tax_id']
                ],
                'providerInfo.specialty' => [
                    'source' => 'provider_specialty',
                    'fallbacks' => ['provider.specialty', 'specialty']
                ],
                
                // Place of Service
                'placeOfService.pos11' => [
                    'source' => 'place_of_service_11',
                    'fallbacks' => ['pos_11', 'office']
                ],
                'placeOfService.pos12' => [
                    'source' => 'place_of_service_12',
                    'fallbacks' => ['pos_12', 'home']
                ],
                'placeOfService.pos22' => [
                    'source' => 'place_of_service_22',
                    'fallbacks' => ['pos_22', 'outpatient_hospital']
                ],
                'placeOfService.pos31' => [
                    'source' => 'place_of_service_31',
                    'fallbacks' => ['pos_31', 'skilled_nursing']
                ],
                'placeOfService.pos32' => [
                    'source' => 'place_of_service_32',
                    'fallbacks' => ['pos_32', 'nursing_facility']
                ],
                
                // Product Information
                'productInfo.amnioBandQ4151' => [
                    'source' => 'product_amnioband',
                    'fallbacks' => ['amnioband_q4151', 'product_q4151']
                ],
                'productInfo.allopatchQ4128' => [
                    'source' => 'product_allopatch',
                    'fallbacks' => ['allopatch_q4128', 'product_q4128']
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

    /**
     * Validate that all required template fields can be filled
     */
    public function validateTemplateCompleteness(array $sourceData, string $manufacturerName): array
    {
        $requiredFields = $this->getRequiredFieldsForManufacturer($manufacturerName);
        $mappingRules = $this->getMappingRulesForTemplate('docuseal_ivr');

        $missing = [];
        $available = [];

        foreach ($requiredFields as $field) {
            $rule = $mappingRules[$field] ?? null;
            if (!$rule) {
                $missing[] = $field;
                continue;
            }

            $value = $this->extractValueByRule($sourceData, $rule);
            if ($value !== null && $value !== '') {
                $available[] = $field;
            } else {
                $missing[] = $field;
            }
        }

        return [
            'completeness_percentage' => count($available) / (count($requiredFields) ?: 1) * 100,
            'available_fields' => $available,
            'missing_fields' => $missing,
            'can_proceed' => count($missing) === 0,
            'critical_missing' => $this->getCriticalMissingFields($missing)
        ];
    }

    /**
     * Get required fields for specific manufacturer
     */
    private function getRequiredFieldsForManufacturer(string $manufacturerName): array
    {
        $commonRequired = [
            'patientInfo.patientName',
            'patientInfo.dateOfBirth',
            'insuranceInfo.primaryInsurance.primaryInsuranceName',
            'insuranceInfo.primaryInsurance.primaryMemberId',
            'providerInfo.providerName',
            'providerInfo.providerNPI'
        ];

        $manufacturerSpecific = [
            'ACZ' => ['facilityInfo.facilityName', 'clinicalInfo.diagnosisCodes'],
            'Advanced Health' => ['facilityInfo.facilityNPI', 'clinicalInfo.woundType'],
            'MiMedx' => ['clinicalInfo.woundLocation', 'clinicalInfo.woundSize'],
            // Add other manufacturer-specific requirements
        ];

        return array_merge($commonRequired, $manufacturerSpecific[$manufacturerName] ?? []);
    }

    /**
     * Identify critical missing fields that block template completion
     */
    private function getCriticalMissingFields(array $missingFields): array
    {
        $critical = [
            'patientInfo.patientName',
            'patientInfo.dateOfBirth',
            'insuranceInfo.primaryInsurance.primaryInsuranceName',
            'providerInfo.providerNPI'
        ];

        return array_intersect($missingFields, $critical);
    }
}

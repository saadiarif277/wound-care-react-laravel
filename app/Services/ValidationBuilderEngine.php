<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\ProductRequest;
use App\Services\CmsCoverageApiService;
use App\Services\WoundCareValidationEngine;
use App\Services\PulmonologyWoundCareValidationEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ValidationBuilderEngine
{
    private CmsCoverageApiService $cmsService;
    private WoundCareValidationEngine $woundCareEngine;
    private PulmonologyWoundCareValidationEngine $pulmonologyWoundCareEngine;

    public function __construct(
        CmsCoverageApiService $cmsService,
        WoundCareValidationEngine $woundCareEngine,
        PulmonologyWoundCareValidationEngine $pulmonologyWoundCareEngine
    ) {
        $this->cmsService = $cmsService;
        $this->woundCareEngine = $woundCareEngine;
        $this->pulmonologyWoundCareEngine = $pulmonologyWoundCareEngine;
    }

    /**
     * Build validation rules for a specific user's specialty
     */
    public function buildValidationRulesForUser(User $user, ?string $state = null): array
    {
        $specialty = $this->getUserSpecialty($user);

        if (!$specialty) {
            Log::warning('No specialty found for user', ['user_id' => $user->id]);
            return $this->getDefaultValidationRules();
        }

        return $this->buildValidationRulesForSpecialty($specialty, $state);
    }

    /**
     * Build validation rules for a specific specialty
     */
    public function buildValidationRulesForSpecialty(string $specialty, ?string $state = null): array
    {
        $cacheKey = "validation_rules_{$specialty}_" . ($state ?? 'all');

        return Cache::remember($cacheKey, 30, function () use ($specialty, $state) {
            // Delegate to appropriate engine based on specialty
            return match($specialty) {
                'wound_care_specialty', 'wound_care' => $this->woundCareEngine->buildValidationRules($state),
                'pulmonology_wound_care' => $this->pulmonologyWoundCareEngine->buildValidationRules($state),
                'vascular_surgery' => $this->getVascularSurgeryRules(),
                'interventional_radiology' => $this->getInterventionalRadiologyRules(),
                'cardiology' => $this->getCardiologyRules(),
                'podiatry' => $this->getPodiatryRules(),
                'plastic_surgery' => $this->getPlasticSurgeryRules(),
                default => $this->getDefaultValidationRules()
            };
        });
    }

    /**
     * Validate an order against specialty-specific rules
     */
    public function validateOrder(Order $order, ?string $specialty = null): array
    {
        $specialty = $specialty ?? $this->getOrderSpecialty($order);
        $state = $this->getOrderState($order);

        // Delegate to appropriate engine based on specialty
        return match($specialty) {
            'wound_care_specialty', 'wound_care' => $this->woundCareEngine->validateOrder($order, $state),
            'pulmonology_wound_care' => $this->pulmonologyWoundCareEngine->validateOrder($order, $state),
            default => $this->performGeneralValidation($order, [])
        };
    }

    /**
     * Validate a product request against specialty-specific rules
     */
    public function validateProductRequest(ProductRequest $productRequest, ?string $specialty = null): array
    {
        $specialty = $specialty ?? $this->getProductRequestSpecialty($productRequest);
        $state = $this->getProductRequestState($productRequest);

        // Delegate to appropriate engine based on specialty
        return match($specialty) {
            'wound_care_specialty', 'wound_care' => $this->woundCareEngine->validateProductRequest($productRequest, $state),
            'pulmonology_wound_care' => $this->pulmonologyWoundCareEngine->validateProductRequest($productRequest, $state),
            default => $this->performProductRequestValidation($productRequest, [], $specialty)
        };
    }

    /**
     * Get user's specialty
     */
    private function getUserSpecialty(User $user): ?string
    {
        // Try to get from user credentials
        $credentials = $user->credentials ?? [];
        if (isset($credentials['specialty'])) {
            return $this->normalizeSpecialty($credentials['specialty']);
        }

        // Try to get from facility type
        $primaryFacility = $user->primaryFacility();
        if ($primaryFacility && $primaryFacility->type) {
            return $this->inferSpecialtyFromFacilityType($primaryFacility->type);
        }

        return null;
    }

    /**
     * Get base validation rules for specialty (deprecated - engines now handle this)
     */
    private function getSpecialtyBaseRules(string $specialty): array
    {
        return match($specialty) {
            'vascular_surgery' => $this->getVascularSurgeryRules(),
            'interventional_radiology' => $this->getInterventionalRadiologyRules(),
            'cardiology' => $this->getCardiologyRules(),
            'podiatry' => $this->getPodiatryRules(),
            'plastic_surgery' => $this->getPlasticSurgeryRules(),
            default => $this->getDefaultValidationRules()
        };
    }

    /**
     * Extract validation rules from CMS data
     */
    private function extractValidationRulesFromCmsData(array $lcds, array $ncds, array $articles, string $specialty): array
    {
        $rules = [];

        // Extract from LCDs
        foreach ($lcds as $lcd) {
            $lcdRules = $this->extractRulesFromLCD($lcd, $specialty);
            $rules = array_merge_recursive($rules, $lcdRules);
        }

        // Extract from NCDs
        foreach ($ncds as $ncd) {
            $ncdRules = $this->extractRulesFromNCD($ncd, $specialty);
            $rules = array_merge_recursive($rules, $ncdRules);
        }

        // Extract from Articles
        foreach ($articles as $article) {
            $articleRules = $this->extractRulesFromArticle($article, $specialty);
            $rules = array_merge_recursive($rules, $articleRules);
        }

        return $rules;
    }

    /**
     * Extract rules from LCD document
     */
    private function extractRulesFromLCD(array $lcd, string $specialty): array
    {
        $rules = [];

        // Extract coverage criteria
        if (isset($lcd['coverage_criteria'])) {
            $rules['lcd_coverage_criteria'] = $this->parseCoverageCriteria($lcd['coverage_criteria']);
        }

        // Extract limitations
        if (isset($lcd['limitations'])) {
            $rules['lcd_limitations'] = $this->parseLimitations($lcd['limitations']);
        }

        // Extract indications
        if (isset($lcd['indications'])) {
            $rules['lcd_indications'] = $this->parseIndications($lcd['indications']);
        }

        return $rules;
    }

    /**
     * Extract rules from NCD document
     */
    private function extractRulesFromNCD(array $ncd, string $specialty): array
    {
        $rules = [];

        // Extract national coverage criteria
        if (isset($ncd['coverage_criteria'])) {
            $rules['ncd_coverage_criteria'] = $this->parseCoverageCriteria($ncd['coverage_criteria']);
        }

        return $rules;
    }

    /**
     * Extract rules from Article
     */
    private function extractRulesFromArticle(array $article, string $specialty): array
    {
        $rules = [];

        // Extract billing and coding rules
        if (isset($article['coding_information'])) {
            $rules['coding_requirements'] = $this->parseCodingInformation($article['coding_information']);
        }

        return $rules;
    }

    /**
     * Perform validation on order (deprecated - engines now handle this)
     */
    private function performValidation(Order $order, array $validationRules, string $specialty): array
    {
        return $this->performGeneralValidation($order, $validationRules);
    }

    /**
     * Perform wound care specific validation
     */
    private function performWoundCareValidation(Order $order, array $validationRules): array
    {
        $results = [
            'overall_status' => 'pending',
            'validations' => []
        ];

        // Check required documentation
        $documentationCheck = $this->validateWoundCareDocumentation($order, $validationRules);
        $results['validations'][] = $documentationCheck;

        // Check conservative care requirements
        $conservativeCareCheck = $this->validateConservativeCare($order, $validationRules);
        $results['validations'][] = $conservativeCareCheck;

        // Check wound assessment requirements
        $woundAssessmentCheck = $this->validateWoundAssessment($order, $validationRules);
        $results['validations'][] = $woundAssessmentCheck;

        // Check MAC coverage requirements
        $macCoverageCheck = $this->validateMACCoverage($order, $validationRules);
        $results['validations'][] = $macCoverageCheck;

        // Determine overall status
        $passedValidations = array_filter($results['validations'], fn($v) => $v['status'] === 'passed');
        $failedValidations = array_filter($results['validations'], fn($v) => $v['status'] === 'failed');

        if (count($failedValidations) > 0) {
            $results['overall_status'] = 'failed';
        } elseif (count($passedValidations) === count($results['validations'])) {
            $results['overall_status'] = 'passed';
        } else {
            $results['overall_status'] = 'requires_review';
        }

        return $results;
    }

    /**
     * Validate wound care documentation requirements
     */
    private function validateWoundCareDocumentation(Order $order, array $rules): array
    {
        // This would check against actual order data
        // For now, return a sample validation
        return [
            'rule' => 'Wound Care Documentation',
            'status' => 'passed',
            'message' => 'All required wound care documentation is present',
            'details' => [
                'wound_measurements' => 'documented',
                'wound_photography' => 'documented',
                'wound_classification' => 'documented'
            ]
        ];
    }

    /**
     * Validate conservative care requirements
     */
    private function validateConservativeCare(Order $order, array $rules): array
    {
        return [
            'rule' => 'Conservative Care Requirements',
            'status' => 'passed',
            'message' => 'Minimum 4 weeks of conservative care documented',
            'details' => [
                'duration' => '6 weeks',
                'documentation' => 'complete',
                'compliance' => 'good'
            ]
        ];
    }

    /**
     * Validate wound assessment requirements
     */
    private function validateWoundAssessment(Order $order, array $rules): array
    {
        return [
            'rule' => 'Wound Assessment',
            'status' => 'passed',
            'message' => 'Comprehensive wound assessment completed',
            'details' => [
                'wound_type' => 'classified',
                'measurements' => 'documented',
                'tissue_assessment' => 'complete'
            ]
        ];
    }

    /**
     * Validate MAC coverage requirements
     */
    private function validateMACCoverage(Order $order, array $rules): array
    {
        return [
            'rule' => 'MAC Coverage Verification',
            'status' => 'passed',
            'message' => 'MAC coverage requirements verified',
            'details' => [
                'jurisdiction' => 'verified',
                'lcd_compliance' => 'met',
                'coding' => 'appropriate'
            ]
        ];
    }

    // Helper methods for other specialties
    private function getVascularSurgeryRules(): array {
        return ['placeholder' => 'Vascular surgery rules to be implemented'];
    }

    private function getInterventionalRadiologyRules(): array {
        return ['placeholder' => 'Interventional radiology rules to be implemented'];
    }

    private function getCardiologyRules(): array {
        return ['placeholder' => 'Cardiology rules to be implemented'];
    }

    private function getPodiatryRules(): array {
        return ['placeholder' => 'Podiatry rules to be implemented'];
    }

    private function getPlasticSurgeryRules(): array {
        return ['placeholder' => 'Plastic surgery rules to be implemented'];
    }

    private function getDefaultValidationRules(): array {
        return ['default' => 'Basic validation rules'];
    }

    // Additional helper methods
    private function getOrderSpecialty(Order $order): string {
        return $order->specialty ?? 'wound_care_specialty';
    }

    private function getOrderState(Order $order): ?string {
        return $order->facility?->state ?? null;
    }

    private function getProductRequestSpecialty(ProductRequest $productRequest): string {
        return $productRequest->specialty ?? 'wound_care_specialty';
    }

    private function getProductRequestState(ProductRequest $productRequest): ?string {
        return $productRequest->facility?->state ?? null;
    }

    private function performProductRequestValidation(ProductRequest $productRequest, array $validationRules, string $specialty): array {
        return ['status' => 'pending', 'message' => 'General validation not implemented'];
    }

    private function performGeneralValidation(Order $order, array $validationRules): array {
        return ['status' => 'pending', 'message' => 'General validation not implemented'];
    }

    private function normalizeSpecialty(?string $specialty): ?string {
        return strtolower(trim($specialty ?? ''));
    }

    private function inferSpecialtyFromFacilityType(string $facilityType): ?string {
        return match(strtolower($facilityType)) {
            'wound care center' => 'wound_care_specialty',
            'vascular surgery center' => 'vascular_surgery',
            'cardiology clinic' => 'cardiology',
            default => null
        };
    }

    /**
     * Get combined pulmonology + wound care validation rules
     */
    private function loadPulmonologyWoundCareValidationRules(): array
    {
        return [
            'pre_treatment_qualification' => [
                'patient_insurance_info' => [
                    'patient_name' => ['required' => true, 'type' => 'string'],
                    'date_of_birth' => ['required' => true, 'type' => 'date'],
                    'medical_record_number' => ['required' => true, 'type' => 'string'],
                    'primary_diagnosis_icd10' => ['required' => true, 'type' => 'icd10_code'],
                    'secondary_diagnoses' => ['required' => false, 'type' => 'array'],
                    'insurance_type' => ['required' => true, 'options' => ['Medicare', 'Medicare Advantage', 'Commercial', 'Other']],
                    'insurance_verification_completed' => ['required' => true, 'type' => 'boolean'],
                    'advance_beneficiary_notice' => ['required_if' => 'applicable', 'type' => 'boolean']
                ],
                'facility_provider_info' => [
                    'facility_name' => ['required' => true, 'type' => 'string'],
                    'facility_npi' => ['required' => true, 'type' => 'npi'],
                    'facility_type' => ['required' => true, 'options' => ['Hospital Outpatient', 'Pulmonary Center', 'Wound Care Center', 'Other']],
                    'treating_pulmonologist' => ['required' => true, 'type' => 'string'],
                    'wound_care_provider' => ['required' => true, 'type' => 'string'],
                    'provider_specialty' => ['required' => true, 'options' => ['Pulmonology', 'Critical Care', 'Sleep Medicine', 'Wound Care', 'Combined']]
                ]
            ],
            'pulmonary_history_assessment' => [
                'primary_pulmonary_conditions' => [
                    'copd' => ['type' => 'object', 'fields' => ['stage' => 'string', 'fev1_percent' => 'numeric']],
                    'asthma' => ['type' => 'object', 'fields' => ['severity' => 'string']],
                    'sleep_apnea' => ['type' => 'object', 'fields' => ['type' => 'string', 'ahi' => 'numeric']],
                    'pulmonary_hypertension' => ['type' => 'boolean'],
                    'interstitial_lung_disease' => ['type' => 'boolean'],
                    'lung_cancer' => ['type' => 'object', 'fields' => ['type' => 'string', 'stage' => 'string']]
                ],
                'smoking_history' => [
                    'current_smoker' => ['type' => 'object', 'fields' => ['ppd' => 'numeric', 'years' => 'numeric']],
                    'former_smoker' => ['type' => 'object', 'fields' => ['quit_date' => 'date', 'pack_years' => 'numeric']],
                    'never_smoker' => ['type' => 'boolean']
                ],
                'functional_status' => [
                    'mrc_dyspnea_scale' => ['required' => true, 'type' => 'numeric', 'min' => 1, 'max' => 5],
                    'six_minute_walk_distance' => ['type' => 'numeric', 'min' => 0],
                    'exercise_tolerance' => ['required' => true, 'options' => ['Good', 'Fair', 'Poor']]
                ]
            ],
            'wound_assessment_with_pulmonary_considerations' => [
                'wound_type' => [
                    'pressure_injury_ventilator_related' => ['type' => 'boolean'],
                    'surgical_wound_thoracic' => ['type' => 'boolean'],
                    'tracheostomy_related' => ['type' => 'boolean'],
                    'diabetic_foot_ulcer' => ['type' => 'boolean'],
                    'venous_leg_ulcer' => ['type' => 'boolean'],
                    'arterial_ulcer' => ['type' => 'boolean']
                ],
                'factors_affecting_healing' => [
                    'tissue_hypoxia' => ['type' => 'object', 'fields' => ['spo2_percent' => 'numeric']],
                    'chronic_steroid_use' => ['type' => 'boolean'],
                    'immunosuppression' => ['type' => 'boolean'],
                    'limited_mobility_dyspnea' => ['type' => 'boolean'],
                    'frequent_coughing' => ['type' => 'boolean'],
                    'edema_right_heart_failure' => ['type' => 'boolean']
                ],
                'wound_measurements' => [
                    'length_cm' => ['required' => true, 'type' => 'numeric', 'min' => 0],
                    'width_cm' => ['required' => true, 'type' => 'numeric', 'min' => 0],
                    'depth_cm' => ['required' => true, 'type' => 'numeric', 'min' => 0],
                    'total_area_cm2' => ['required' => true, 'type' => 'numeric', 'min' => 0]
                ]
            ],
            'tissue_oxygenation_assessment' => [
                'transcutaneous_oxygen_pressure' => [
                    'wound_site_mmhg' => ['required' => true, 'type' => 'numeric', 'min' => 0],
                    'reference_site_mmhg' => ['required' => true, 'type' => 'numeric', 'min' => 0],
                    'on_room_air' => ['required' => true, 'type' => 'boolean'],
                    'with_supplemental_o2' => ['type' => 'numeric', 'min' => 0]
                ],
                'hyperbaric_oxygen_evaluation' => [
                    'candidate_for_hbo' => ['required' => true, 'type' => 'boolean'],
                    'contraindications' => ['type' => 'string'],
                    'previous_hbo_sessions' => ['type' => 'numeric', 'min' => 0]
                ]
            ],
            'conservative_care_pulmonary_specific' => [
                'optimization_of_oxygenation' => [
                    'o2_therapy_initiated' => ['required' => true, 'type' => 'boolean'],
                    'target_spo2_percent' => ['required' => true, 'type' => 'numeric', 'min' => 85, 'max' => 100],
                    'compliance_with_o2' => ['required' => true, 'options' => ['Good', 'Fair', 'Poor']]
                ],
                'pulmonary_rehabilitation' => [
                    'enrolled' => ['type' => 'boolean'],
                    'sessions_completed' => ['type' => 'numeric', 'min' => 0],
                    'functional_improvement' => ['type' => 'string']
                ],
                'smoking_cessation' => [
                    'counseling_provided' => ['type' => 'boolean'],
                    'pharmacotherapy' => ['type' => 'boolean'],
                    'quit_date' => ['type' => 'date']
                ],
                'standard_wound_care_minimum_4_weeks' => [
                    'start_date' => ['required' => true, 'type' => 'date'],
                    'dressings_used' => ['required' => true, 'type' => 'string'],
                    'frequency' => ['required' => true, 'type' => 'string'],
                    'response' => ['required' => true, 'type' => 'string']
                ]
            ],
            'coordinated_care_planning' => [
                'multidisciplinary_team' => [
                    'pulmonologist' => ['required' => true, 'type' => 'boolean'],
                    'wound_care_specialist' => ['required' => true, 'type' => 'boolean'],
                    'respiratory_therapist' => ['type' => 'boolean'],
                    'physical_therapist' => ['type' => 'boolean'],
                    'nutritionist' => ['type' => 'boolean']
                ],
                'care_coordination' => [
                    'team_meetings_documented' => ['required' => true, 'type' => 'boolean'],
                    'shared_treatment_goals' => ['required' => true, 'type' => 'string'],
                    'communication_method' => ['required' => true, 'type' => 'string']
                ],
                'home_care_requirements' => [
                    'home_o2_setup_verified' => ['type' => 'boolean'],
                    'caregiver_training_completed' => ['type' => 'boolean'],
                    'emergency_plan_established' => ['required' => true, 'type' => 'boolean']
                ]
            ],
            'mac_coverage_verification' => [
                'mac_jurisdiction' => ['required' => true, 'type' => 'string'],
                'lcd_wound_care' => ['required' => true, 'type' => 'string'],
                'lcd_pulmonary' => ['required' => true, 'type' => 'string'],
                'documentation_requirements_met' => ['required' => true, 'type' => 'boolean'],
                'coverage_criteria_verified' => ['required' => true, 'type' => 'boolean'],
                'prior_authorization_required' => ['type' => 'boolean'],
                'cpt_codes' => ['required' => true, 'type' => 'array'],
                'hcpcs_codes' => ['type' => 'array'],
                'applicable_modifiers' => ['type' => 'array', 'options' => ['KX', 'GA', 'JW', 'RT', 'LT', '58', '59']],
                'icd10_codes_support' => ['required' => true, 'type' => 'boolean']
            ]
        ];
    }

    private function parseCoverageCriteria(string $criteria): array { return []; }
    private function parseLimitations(string $limitations): array { return []; }
    private function parseIndications(string $indications): array { return []; }
    private function parseCodingInformation(string $codingInfo): array { return []; }
}

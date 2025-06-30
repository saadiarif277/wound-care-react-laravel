<?php

namespace App\Services\Insurance;

use App\Services\UnifiedFieldMappingService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class InsuranceDataNormalizer
{
    private UnifiedFieldMappingService $fieldMappingService;
    private array $normalizationRules;

    public function __construct(UnifiedFieldMappingService $fieldMappingService)
    {
        $this->fieldMappingService = $fieldMappingService;
        $this->loadNormalizationRules();
    }

    /**
     * Normalize insurance data from any source into a standard format
     */
    public function normalize(array $data, string $source): array
    {
        // Apply source-specific normalization
        $normalized = match($source) {
            'insurance_card' => $this->normalizeFromInsuranceCard($data),
            'docuseal_ivr' => $this->normalizeFromDocuSeal($data),
            'quick_request' => $this->normalizeFromQuickRequest($data),
            'eligibility_response' => $this->normalizeFromEligibilityResponse($data),
            'manual_entry' => $this->normalizeFromManualEntry($data),
            default => $data
        };

        // Apply common normalizations
        $normalized = $this->applyCommonNormalizations($normalized);

        // Add metadata
        $normalized['_metadata'] = [
            'source' => $source,
            'normalized_at' => now()->toIso8601String(),
            'confidence_score' => $this->calculateConfidenceScore($normalized, $source)
        ];

        return $normalized;
    }

    /**
     * Merge data from multiple sources intelligently
     */
    public function mergeFromMultipleSources(array $sources): array
    {
        $merged = [];
        $fieldConfidence = [];

        foreach ($sources as $source => $data) {
            $normalized = $this->normalize($data, $source);
            $confidence = $normalized['_metadata']['confidence_score'] ?? 0;

            foreach ($normalized as $field => $value) {
                if ($field === '_metadata') continue;

                // Keep the value with highest confidence
                if (!isset($fieldConfidence[$field]) || $confidence > $fieldConfidence[$field]) {
                    $merged[$field] = $value;
                    $fieldConfidence[$field] = $confidence;
                }
            }
        }

        // Add merge metadata
        $merged['_metadata'] = [
            'merged_from' => array_keys($sources),
            'merged_at' => now()->toIso8601String(),
            'field_sources' => array_map(function($field) use ($sources, $merged) {
                foreach ($sources as $source => $data) {
                    $normalized = $this->normalize($data, $source);
                    if (isset($normalized[$field]) && $normalized[$field] === $merged[$field]) {
                        return $source;
                    }
                }
                return 'unknown';
            }, array_keys($merged))
        ];

        return $merged;
    }

    /**
     * Normalize data from insurance card OCR
     */
    private function normalizeFromInsuranceCard(array $data): array
    {
        return [
            // Patient Information
            'patient_first_name' => $this->extractName($data, 'first'),
            'patient_last_name' => $this->extractName($data, 'last'),
            'patient_member_id' => $this->extractMemberId($data),

            // Insurance Information
            'payer_name' => $this->extractPayerName($data),
            'payer_id' => $this->extractPayerId($data),
            'group_number' => data_get($data, 'group_number') ?? data_get($data, 'group'),
            'plan_type' => $this->detectPlanType($data),
            'payer_phone' => $this->extractPhoneNumber($data, 'payer'),

            // Additional fields from OCR
            'rx_bin' => data_get($data, 'rx_bin'),
            'rx_pcn' => data_get($data, 'rx_pcn'),
            'rx_group' => data_get($data, 'rx_group'),

            // Coverage dates if available
            'effective_date' => $this->parseDate(data_get($data, 'effective_date')),
            'termination_date' => $this->parseDate(data_get($data, 'termination_date')),
        ];
    }

    /**
     * Normalize data from DocuSeal IVR response
     */
    private function normalizeFromDocuSeal(array $data): array
    {
        // DocuSeal returns data in a nested structure
        $fields = data_get($data, 'submission.fields', []);

        // Convert to flat array for mapping
        $flatData = [];
        foreach ($fields as $field) {
            $flatData[$field['name']] = $field['value'];
        }

        // Use UnifiedFieldMappingService to map to canonical fields
        return $this->fieldMappingService->mapToCanonicalFields($flatData);
    }

    /**
     * Normalize data from quick request form
     */
    private function normalizeFromQuickRequest(array $data): array
    {
        return [
            // Direct mappings
            'patient_first_name' => data_get($data, 'patient_first_name'),
            'patient_last_name' => data_get($data, 'patient_last_name'),
            'patient_dob' => $this->parseDate(data_get($data, 'patient_dob')),
            'patient_member_id' => data_get($data, 'patient_member_id'),

            // Insurance mappings
            'payer_name' => data_get($data, 'payer_name'),
            'payer_id' => data_get($data, 'payer_id'),
            'group_number' => data_get($data, 'group_number'),

            // Provider/Facility
            'provider_npi' => data_get($data, 'provider_npi'),
            'provider_name' => data_get($data, 'provider_name'),
            'facility_name' => data_get($data, 'facility_name'),
            'facility_npi' => data_get($data, 'facility_npi'),
        ];
    }

    /**
     * Normalize data from eligibility response
     */
    private function normalizeFromEligibilityResponse(array $data): array
    {
        return [
            // Coverage information
            'is_eligible' => data_get($data, 'eligible', false),
            'coverage_status' => data_get($data, 'status'),
            'payer_name' => data_get($data, 'payer.name'),
            'payer_id' => data_get($data, 'payer.id'),

            // Benefits
            'copay_amount' => $this->extractMoneyValue(data_get($data, 'copay')),
            'deductible_amount' => $this->extractMoneyValue(data_get($data, 'deductible')),
            'deductible_met' => $this->extractMoneyValue(data_get($data, 'deductible_met')),
            'out_of_pocket_max' => $this->extractMoneyValue(data_get($data, 'out_of_pocket_max')),
            'out_of_pocket_met' => $this->extractMoneyValue(data_get($data, 'out_of_pocket_met')),

            // Plan details
            'plan_name' => data_get($data, 'plan.name'),
            'plan_type' => data_get($data, 'plan.type'),
            'network_status' => data_get($data, 'network_status', 'in_network'),
        ];
    }

    /**
     * Common normalizations applied to all sources
     */
    private function applyCommonNormalizations(array $data): array
    {
        // Normalize phone numbers
        foreach (['payer_phone', 'patient_phone', 'provider_phone'] as $phoneField) {
            if (isset($data[$phoneField])) {
                $data[$phoneField] = $this->normalizePhoneNumber($data[$phoneField]);
            }
        }

        // Normalize dates
        foreach (['patient_dob', 'effective_date', 'termination_date'] as $dateField) {
            if (isset($data[$dateField])) {
                $data[$dateField] = $this->parseDate($data[$dateField]);
            }
        }

        // Normalize names
        foreach (['patient_first_name', 'patient_last_name', 'provider_name'] as $nameField) {
            if (isset($data[$nameField])) {
                $data[$nameField] = Str::title(trim($data[$nameField]));
            }
        }

        // Normalize IDs (remove special characters)
        foreach (['patient_member_id', 'group_number', 'provider_npi'] as $idField) {
            if (isset($data[$idField])) {
                $data[$idField] = preg_replace('/[^A-Za-z0-9]/', '', $data[$idField]);
            }
        }

        // Add MAC jurisdiction if we have state
        if (isset($data['patient_state']) || isset($data['facility_state'])) {
            $data['mac_jurisdiction'] = $this->determineMacJurisdiction(
                $data['patient_state'] ?? $data['facility_state']
            );
        }

        return $data;
    }

    // ... [All the private methods from before - I'll continue with the rest]

    /**
     * Extract name parts from various formats
     */
    private function extractName(array $data, string $part): ?string
    {
        $fullName = data_get($data, 'patient_name') ?? data_get($data, 'member_name');

        if (!$fullName) {
            return data_get($data, "patient_{$part}_name");
        }

        // Handle "LASTNAME, FIRSTNAME" format
        if (str_contains($fullName, ',')) {
            $parts = explode(',', $fullName);
            return $part === 'last' ? trim($parts[0]) : trim($parts[1] ?? '');
        }

        // Handle "FIRSTNAME LASTNAME" format
        $parts = explode(' ', $fullName);
        if ($part === 'first') {
            return $parts[0] ?? null;
        }

        // Last name is everything after first name
        return implode(' ', array_slice($parts, 1));
    }

    /**
     * Extract member ID from various fields
     */
    private function extractMemberId(array $data): ?string
    {
        $possibleFields = [
            'member_id', 'patient_member_id', 'subscriber_id',
            'id_number', 'member_number', 'policy_number'
        ];

        foreach ($possibleFields as $field) {
            if ($value = data_get($data, $field)) {
                return preg_replace('/[^A-Za-z0-9]/', '', $value);
            }
        }

        return null;
    }

    /**
     * Extract payer name and normalize it
     */
    private function extractPayerName(array $data): ?string
    {
        $payerName = data_get($data, 'payer_name')
            ?? data_get($data, 'insurance_company')
            ?? data_get($data, 'carrier_name');

        if (!$payerName) {
            return null;
        }

        // Normalize common variations
        $normalizations = [
            'BCBS' => 'Blue Cross Blue Shield',
            'BC/BS' => 'Blue Cross Blue Shield',
            'UHC' => 'UnitedHealthcare',
            'United Healthcare' => 'UnitedHealthcare',
            'Cigna Healthcare' => 'Cigna',
            'Aetna Inc' => 'Aetna',
        ];

        foreach ($normalizations as $short => $full) {
            if (stripos($payerName, $short) !== false) {
                return $full;
            }
        }

        return Str::title(trim($payerName));
    }

    /**
     * Extract payer ID from data
     */
    private function extractPayerId(array $data): ?string
    {
        // Direct payer ID
        if ($payerId = data_get($data, 'payer_id')) {
            return $payerId;
        }

        // Try to determine from payer name
        $payerName = $this->extractPayerName($data);
        if ($payerName) {
            return $this->lookupPayerIdByName($payerName);
        }

        return null;
    }

    /**
     * Detect plan type from various indicators
     */
    private function detectPlanType(array $data): ?string
    {
        $planIndicators = data_get($data, 'plan_type')
            ?? data_get($data, 'plan_name')
            ?? data_get($data, 'coverage_type');

        if (!$planIndicators) {
            return null;
        }

        $planIndicators = strtoupper($planIndicators);

        if (str_contains($planIndicators, 'PPO')) return 'PPO';
        if (str_contains($planIndicators, 'HMO')) return 'HMO';
        if (str_contains($planIndicators, 'POS')) return 'POS';
        if (str_contains($planIndicators, 'EPO')) return 'EPO';
        if (str_contains($planIndicators, 'MEDICARE')) return 'Medicare';
        if (str_contains($planIndicators, 'MEDICAID')) return 'Medicaid';

        return 'Other';
    }

    /**
     * Extract and normalize phone numbers
     */
    private function extractPhoneNumber(array $data, string $type): ?string
    {
        $phoneFields = match($type) {
            'payer' => ['payer_phone', 'insurance_phone', 'carrier_phone', 'customer_service'],
            'patient' => ['patient_phone', 'member_phone', 'contact_phone'],
            default => [$type . '_phone']
        };

        foreach ($phoneFields as $field) {
            if ($phone = data_get($data, $field)) {
                return $this->normalizePhoneNumber($phone);
            }
        }

        return null;
    }

    /**
     * Normalize phone number to standard format
     */
    private function normalizePhoneNumber(?string $phone): ?string
    {
        if (!$phone) return null;

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Handle common formats
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }

        if (strlen($phone) === 11 && $phone[0] === '1') {
            return $this->normalizePhoneNumber(substr($phone, 1));
        }

        return $phone;
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($date): ?string
    {
        if (!$date) return null;

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            // Try common formats
            $formats = ['m/d/Y', 'd/m/Y', 'Y-m-d', 'm-d-Y', 'd-m-Y'];

            foreach ($formats as $format) {
                try {
                    return \Carbon\Carbon::createFromFormat($format, $date)->format('Y-m-d');
                } catch (\Exception $e) {
                    continue;
                }
            }

            return null;
        }
    }

    /**
     * Extract money value from various formats
     */
    private function extractMoneyValue($value): ?float
    {
        if (!$value) return null;

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Remove currency symbols and convert
        $value = preg_replace('/[^0-9.]/', '', $value);
        return $value ? (float) $value : null;
    }


    /**
     * Determine MAC jurisdiction from state
     */
    private function determineMacJurisdiction(string $state): string
    {
        $jurisdictions = [
            'JE' => ['CA', 'HI', 'NV', 'AS', 'GU', 'MP'],
            'JF' => ['AK', 'AZ', 'ID', 'MT', 'ND', 'OR', 'SD', 'UT', 'WA', 'WY'],
            'JH' => ['AR', 'CO', 'LA', 'MS', 'NM', 'OK', 'TX'],
            'JJ' => ['AL', 'FL', 'GA', 'KY', 'NC', 'SC', 'TN', 'VA', 'WV'],
            'JK' => ['CT', 'DE', 'DC', 'ME', 'MD', 'MA', 'NH', 'NJ', 'NY', 'PA', 'RI', 'VT'],
            'JL' => ['IA', 'IL', 'IN', 'KS', 'MI', 'MN', 'MO', 'NE', 'OH', 'WI'],
        ];

        foreach ($jurisdictions as $jurisdiction => $states) {
            if (in_array(strtoupper($state), $states)) {
                return $jurisdiction;
            }
        }

        return 'Unknown';
    }

    /**
     * Calculate confidence score for normalized data
     */
    private function calculateConfidenceScore(array $data, string $source): float
    {
        $requiredFields = [
            'patient_first_name', 'patient_last_name', 'patient_member_id',
            'payer_name', 'payer_id'
        ];

        $filledFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($data[$field])) {
                $filledFields++;
            }
        }

        $baseScore = $filledFields / count($requiredFields);

        // Adjust score based on source reliability
        $sourceMultipliers = [
            'eligibility_response' => 1.0,
            'docuseal_ivr' => 0.95,
            'insurance_card' => 0.9,
            'quick_request' => 0.85,
            'manual_entry' => 0.8,
        ];

        $multiplier = $sourceMultipliers[$source] ?? 0.7;

        return min(1.0, $baseScore * $multiplier);
    }

    /**
     * Lookup payer ID by name from database or config
     */
    private function lookupPayerIdByName(string $payerName): ?string
    {
        // This would typically query a database table
        // For now, use a static mapping
        $payerIds = [
            'Medicare' => 'MEDICARE',
            'Blue Cross Blue Shield' => 'BCBS',
            'UnitedHealthcare' => 'UHC001',
            'Aetna' => 'AETNA',
            'Cigna' => 'CIGNA',
            'Humana' => 'HUMANA',
            'Anthem' => 'ANTHEM',
        ];

        return $payerIds[$payerName] ?? null;
    }

    /**
     * Load normalization rules from config or database
     */
    private function loadNormalizationRules(): void
    {
        $this->normalizationRules = [
            'field_mappings' => [
                'insurance_card' => [
                    'member_id' => ['patient_member_id', 'id_number', 'subscriber_id'],
                    'group_number' => ['group_no', 'group_id', 'grp'],
                ],
                'docuseal' => [
                    'patient_name' => ['full_name', 'name'],
                    'insurance_company' => ['payer_name', 'carrier'],
                ],
            ],
            'value_normalizations' => [
                'payer_names' => [
                    'BCBS' => 'Blue Cross Blue Shield',
                    'UHC' => 'UnitedHealthcare',
                ],
                'states' => [
                    'California' => 'CA',
                    'New York' => 'NY',
                ],
            ],
        ];
    }

    /**
     * Normalize manual entry data
     */
    private function normalizeFromManualEntry(array $data): array
    {
        // Manual entry is usually already in our expected format
        // Just apply common normalizations
        return $data;
    }
}
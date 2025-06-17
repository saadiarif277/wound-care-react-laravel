<?php

namespace App\Services;

use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IvrFieldDiscoveryService
{
    private ?IvrFieldMappingService $mappingService = null;
    
    public function __construct()
    {
        // Make IvrFieldMappingService optional to avoid circular dependencies
        try {
            $this->mappingService = app(IvrFieldMappingService::class);
        } catch (\Exception $e) {
            Log::warning('IvrFieldMappingService not available for discovery service');
        }
    }

    /**
     * Generate mapping suggestions for extracted fields
     */
    public function generateMappingSuggestions(array $extractedFields, string $templateId): array
    {
        $suggestions = [];
        
        // Get existing template mappings
        $template = DocusealTemplate::find($templateId);
        $existingMappings = $template ? ($template->field_mappings ?? []) : [];
        
        foreach ($extractedFields as $field) {
            $suggestion = [
                'ivr_field_name' => $field['field_name'],
                'original_text' => $field['original_text'],
                'field_type' => $field['field_type'],
                'category' => $field['category'],
                'is_checkbox' => $field['is_checkbox'],
                'suggested_mapping' => null,
                'mapping_type' => null,
                'confidence' => 0,
                'is_mapped' => false,
                'current_mapping' => null
            ];
            
            // Check if already mapped
            if (isset($existingMappings[$field['field_name']])) {
                $suggestion['is_mapped'] = true;
                $suggestion['current_mapping'] = $existingMappings[$field['field_name']];
            } else {
                // Generate mapping suggestion
                $this->suggestMapping($field, $suggestion);
            }
            
            $suggestions[] = $suggestion;
        }
        
        return $suggestions;
    }

    /**
     * Suggest mapping for a field
     */
    private function suggestMapping(array $field, array &$suggestion): void
    {
        // Product checkbox mappings
        if ($field['is_checkbox'] && $field['category'] === 'product') {
            $qCodeMatch = preg_match('/Q(\d{4})/', $field['field_name'], $matches);
            if ($qCodeMatch) {
                $suggestion['suggested_mapping'] = null; // No direct DB mapping
                $suggestion['mapping_type'] = 'product_checkbox';
                $suggestion['confidence'] = 0.95;
                return;
            }
        }
        
        // Place of Service checkboxes
        if ($field['is_checkbox'] && preg_match('/POS\s*(\d+)/', $field['field_name'], $matches)) {
            $suggestion['suggested_mapping'] = 'place_of_service';
            $suggestion['mapping_type'] = 'pos_checkbox';
            $suggestion['confidence'] = 0.9;
            return;
        }
        
        // Direct field mappings based on common patterns
        $directMappings = $this->getDirectFieldMappings();
        
        foreach ($directMappings as $pattern => $mapping) {
            if (preg_match($pattern, $field['field_name'])) {
                $suggestion['suggested_mapping'] = $mapping['field'];
                $suggestion['mapping_type'] = $mapping['type'];
                $suggestion['confidence'] = $mapping['confidence'];
                return;
            }
        }
        
        // Multiple field pattern matching (e.g., Physician NPI 1-7)
        if (preg_match('/^(Physician|Provider)\s+NPI\s+(\d+)$/i', $field['field_name'], $matches)) {
            $index = intval($matches[2]);
            $suggestion['suggested_mapping'] = "provider_npi_{$index}";
            $suggestion['mapping_type'] = 'indexed_field';
            $suggestion['confidence'] = 0.85;
            return;
        }
        
        if (preg_match('/^Facility\s+NPI\s+(\d+)$/i', $field['field_name'], $matches)) {
            $index = intval($matches[1]);
            $suggestion['suggested_mapping'] = "facility_npi_{$index}";
            $suggestion['mapping_type'] = 'indexed_field';
            $suggestion['confidence'] = 0.85;
            return;
        }
        
        // Fuzzy matching for similar field names
        $this->fuzzyMatchField($field, $suggestion);
    }

    /**
     * Get direct field mapping patterns
     */
    private function getDirectFieldMappings(): array
    {
        return [
            '/^Physician\s+Name$/i' => [
                'field' => 'provider_name',
                'type' => 'direct',
                'confidence' => 0.95
            ],
            '/^Provider\s+Name$/i' => [
                'field' => 'provider_name',
                'type' => 'direct',
                'confidence' => 0.95
            ],
            '/^Physician\s+Specialty$/i' => [
                'field' => 'provider_specialty',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^NPI$/i' => [
                'field' => 'provider_npi',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^Tax\s+ID$/i' => [
                'field' => 'provider_tax_id',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^PTAN$/i' => [
                'field' => 'provider_ptan',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^Medicaid\s+#$/i' => [
                'field' => 'provider_medicaid_number',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^Facility\s+Name$/i' => [
                'field' => 'facility_name',
                'type' => 'direct',
                'confidence' => 0.95
            ],
            '/^Facility\s+Address$/i' => [
                'field' => 'facility_address',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^Patient\s+Name$/i' => [
                'field' => 'patient_name',
                'type' => 'direct',
                'confidence' => 0.95
            ],
            '/^Patient\s+DOB$/i' => [
                'field' => 'patient_dob',
                'type' => 'direct',
                'confidence' => 0.95
            ],
            '/^Primary\s+Insurance$/i' => [
                'field' => 'primary_insurance_name',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^Primary\s+Policy\s+Number$/i' => [
                'field' => 'primary_policy_number',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^ICD-?10\s+Codes?$/i' => [
                'field' => 'diagnosis_codes',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^CPT\s+Codes?$/i' => [
                'field' => 'application_cpt_codes',
                'type' => 'direct',
                'confidence' => 0.9
            ],
            '/^Wound\s+Size$/i' => [
                'field' => 'wound_size_total',
                'type' => 'direct',
                'confidence' => 0.85
            ],
            '/^Wound\s+Location$/i' => [
                'field' => 'wound_location',
                'type' => 'direct',
                'confidence' => 0.9
            ]
        ];
    }

    /**
     * Fuzzy match field names
     */
    private function fuzzyMatchField(array $field, array &$suggestion): void
    {
        $systemFields = $this->getSystemFields();
        $fieldNameLower = strtolower($field['field_name']);
        
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($systemFields as $systemField => $description) {
            $systemFieldLower = strtolower($systemField);
            
            // Calculate similarity score
            $score = 0;
            
            // Exact match
            if ($fieldNameLower === $systemFieldLower) {
                $score = 1.0;
            } else {
                // Check for key terms
                $fieldTerms = explode(' ', $fieldNameLower);
                $systemTerms = explode('_', $systemFieldLower);
                
                foreach ($fieldTerms as $term) {
                    foreach ($systemTerms as $sysTerm) {
                        if (similar_text($term, $sysTerm) > 3) {
                            $score += 0.2;
                        }
                    }
                }
                
                // Levenshtein distance
                $distance = levenshtein($fieldNameLower, $systemFieldLower);
                if ($distance < 10) {
                    $score += (10 - $distance) / 20;
                }
            }
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $systemField;
            }
        }
        
        if ($bestScore > 0.5) {
            $suggestion['suggested_mapping'] = $bestMatch;
            $suggestion['mapping_type'] = 'fuzzy';
            $suggestion['confidence'] = min($bestScore, 0.7); // Cap fuzzy match confidence
        }
    }

    /**
     * Get available system fields
     */
    private function getSystemFields(): array
    {
        return [
            // Provider fields
            'provider_name' => 'Provider full name',
            'provider_npi' => 'Provider NPI number',
            'provider_tax_id' => 'Provider tax ID',
            'provider_ptan' => 'Provider PTAN',
            'provider_medicaid_number' => 'Provider Medicaid number',
            'provider_specialty' => 'Provider specialty',
            'provider_phone' => 'Provider phone',
            'provider_fax' => 'Provider fax',
            'provider_address' => 'Provider address',
            
            // Facility fields
            'facility_name' => 'Facility name',
            'facility_npi' => 'Facility NPI',
            'facility_tax_id' => 'Facility tax ID',
            'facility_ptan' => 'Facility PTAN',
            'facility_address' => 'Facility address',
            'facility_city' => 'Facility city',
            'facility_state' => 'Facility state',
            'facility_zip' => 'Facility ZIP code',
            'facility_contact_name' => 'Facility contact name',
            'facility_contact_phone' => 'Facility contact phone',
            'facility_contact_email' => 'Facility contact email',
            
            // Patient fields
            'patient_name' => 'Patient name',
            'patient_dob' => 'Patient date of birth',
            'patient_gender' => 'Patient gender',
            'patient_address' => 'Patient address',
            'patient_city' => 'Patient city',
            'patient_state' => 'Patient state',
            'patient_zip' => 'Patient ZIP',
            'patient_phone' => 'Patient phone',
            
            // Insurance fields
            'primary_insurance_name' => 'Primary insurance name',
            'primary_policy_number' => 'Primary policy number',
            'primary_group_number' => 'Primary group number',
            'primary_payer_phone' => 'Primary payer phone',
            'secondary_insurance_name' => 'Secondary insurance name',
            'secondary_policy_number' => 'Secondary policy number',
            
            // Clinical fields
            'wound_type' => 'Wound type',
            'wound_location' => 'Wound location',
            'wound_size_length' => 'Wound length',
            'wound_size_width' => 'Wound width',
            'wound_size_total' => 'Total wound size',
            'diagnosis_codes' => 'ICD-10 diagnosis codes',
            'application_cpt_codes' => 'CPT codes',
            'place_of_service' => 'Place of service',
            
            // Status fields
            'snf_status' => 'SNF status',
            'hospice_status' => 'Hospice status',
            'global_period_status' => 'Global period status'
        ];
    }

    /**
     * Apply bulk mapping patterns
     */
    public function applyBulkMappingPatterns(array $suggestions, array $patterns): array
    {
        foreach ($suggestions as &$suggestion) {
            if ($suggestion['is_mapped']) {
                continue;
            }
            
            foreach ($patterns as $pattern) {
                if ($this->matchesPattern($suggestion, $pattern)) {
                    $suggestion['suggested_mapping'] = $this->generateMappingFromPattern($suggestion, $pattern);
                    $suggestion['mapping_type'] = 'pattern';
                    $suggestion['confidence'] = $pattern['confidence'] ?? 0.8;
                }
            }
        }
        
        return $suggestions;
    }

    /**
     * Check if suggestion matches a pattern
     */
    private function matchesPattern(array $suggestion, array $pattern): bool
    {
        if (isset($pattern['field_pattern'])) {
            return preg_match($pattern['field_pattern'], $suggestion['ivr_field_name']);
        }
        
        if (isset($pattern['category']) && $suggestion['category'] === $pattern['category']) {
            return true;
        }
        
        return false;
    }

    /**
     * Generate mapping from pattern
     */
    private function generateMappingFromPattern(array $suggestion, array $pattern): string
    {
        if (isset($pattern['mapping_template'])) {
            // Extract any numbers or identifiers from field name
            preg_match('/\d+/', $suggestion['ivr_field_name'], $matches);
            $index = $matches[0] ?? '1';
            
            return str_replace('{index}', $index, $pattern['mapping_template']);
        }
        
        return $pattern['mapping'] ?? '';
    }

    /**
     * Get summary statistics for field discovery
     */
    public function getDiscoverySummary(array $extractedFields, array $suggestions): array
    {
        $totalFields = count($extractedFields);
        $mappedFields = count(array_filter($suggestions, fn($s) => $s['is_mapped']));
        $suggestedFields = count(array_filter($suggestions, fn($s) => !$s['is_mapped'] && $s['suggested_mapping']));
        $unmappedFields = count(array_filter($suggestions, fn($s) => !$s['is_mapped'] && !$s['suggested_mapping']));
        
        $categoryCounts = [];
        foreach ($extractedFields as $field) {
            $category = $field['category'];
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
        }
        
        return [
            'total_fields' => $totalFields,
            'mapped_fields' => $mappedFields,
            'suggested_fields' => $suggestedFields,
            'unmapped_fields' => $unmappedFields,
            'mapping_percentage' => $totalFields > 0 ? round(($mappedFields / $totalFields) * 100, 1) : 0,
            'categories' => $categoryCounts,
            'has_product_checkboxes' => count(array_filter($extractedFields, fn($f) => $f['is_checkbox'] && $f['category'] === 'product')) > 0,
            'has_multiple_npis' => count(array_filter($extractedFields, fn($f) => preg_match('/NPI.*\d+/', $f['field_name']))) > 1
        ];
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Canonical Field Mapping Service
 * 
 * Manages CSV-based field mappings for LLM-driven form pre-fill
 * Provides confidence-based mapping with validation and fallback logic
 */
class CanonicalFieldMappingService
{
    private string $csvPath;
    private array $mappingCache = [];
    private const CACHE_KEY = 'canonical_field_mappings';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct()
    {
        $this->csvPath = base_path('docs/mapping-final/canonical_field_mapping_updated.csv');
    }

    /**
     * Get field mappings for a specific form
     */
    public function getMappingsForForm(string $formName, string $documentType = 'IVR'): array
    {
        $allMappings = $this->loadMappings();
        $formKey = $this->normalizeFormName($formName, $documentType);
        
        $formMappings = $allMappings->filter(function ($mapping) use ($formKey) {
            return $this->normalizeFormName($mapping['form_name']) === $formKey;
        });

        return $this->organizeMappings($formMappings);
    }

    /**
     * Get canonical field mapping for a specific field
     */
    public function getCanonicalMapping(string $formName, string $fieldKey, string $documentType = 'IVR'): ?array
    {
        $formMappings = $this->getMappingsForForm($formName, $documentType);
        
        // Try exact field key match first
        if (isset($formMappings['field_mappings'][$fieldKey])) {
            return $formMappings['field_mappings'][$fieldKey];
        }

        // Try fuzzy matching
        return $this->findFuzzyMatch($formMappings['field_mappings'], $fieldKey);
    }

    /**
     * Get high-confidence mappings only
     */
    public function getHighConfidenceMappings(string $formName, string $documentType = 'IVR', float $minConfidence = 0.8): array
    {
        $mappings = $this->getMappingsForForm($formName, $documentType);
        
        return array_filter($mappings['field_mappings'], function ($mapping) use ($minConfidence) {
            return ($mapping['confidence'] ?? 0) >= $minConfidence && $mapping['mapping_status'] === 'mapped';
        });
    }

    /**
     * Validate field mapping consistency
     */
    public function validateMappings(string $formName, string $documentType = 'IVR'): array
    {
        $mappings = $this->getMappingsForForm($formName, $documentType);
        $issues = [];

        foreach ($mappings['field_mappings'] as $fieldKey => $mapping) {
            // Check for suspicious mappings
            if ($this->isSuspiciousMapping($fieldKey, $mapping['canonical_key'])) {
                $issues[] = [
                    'type' => 'suspicious_mapping',
                    'field_key' => $fieldKey,
                    'canonical_key' => $mapping['canonical_key'],
                    'suggestion' => $this->suggestCorrection($fieldKey, $mapping['canonical_key'])
                ];
            }

            // Check for unmapped required fields
            if ($mapping['required'] && $mapping['mapping_status'] === 'unmapped') {
                $issues[] = [
                    'type' => 'unmapped_required',
                    'field_key' => $fieldKey,
                    'field_label' => $mapping['field_label']
                ];
            }
        }

        return $issues;
    }

    /**
     * Get mapping statistics for a form
     */
    public function getMappingStats(string $formName, string $documentType = 'IVR'): array
    {
        $mappings = $this->getMappingsForForm($formName, $documentType);
        $fieldMappings = $mappings['field_mappings'];
        
        $total = count($fieldMappings);
        $mapped = count(array_filter($fieldMappings, fn($m) => $m['mapping_status'] === 'mapped'));
        $highConfidence = count(array_filter($fieldMappings, fn($m) => ($m['confidence'] ?? 0) >= 0.8));
        $required = count(array_filter($fieldMappings, fn($m) => $m['required']));
        $requiredMapped = count(array_filter($fieldMappings, fn($m) => $m['required'] && $m['mapping_status'] === 'mapped'));

        return [
            'total_fields' => $total,
            'mapped_fields' => $mapped,
            'unmapped_fields' => $total - $mapped,
            'mapping_percentage' => $total > 0 ? round(($mapped / $total) * 100, 2) : 0,
            'high_confidence_fields' => $highConfidence,
            'confidence_percentage' => $total > 0 ? round(($highConfidence / $total) * 100, 2) : 0,
            'required_fields' => $required,
            'required_mapped' => $requiredMapped,
            'required_mapping_percentage' => $required > 0 ? round(($requiredMapped / $required) * 100, 2) : 0
        ];
    }

    /**
     * Get all supported forms
     */
    public function getSupportedForms(): array
    {
        $allMappings = $this->loadMappings();
        
        return $allMappings->groupBy('form_name')
            ->map(function ($formMappings, $formName) {
                $stats = $this->getMappingStats($formName);
                return [
                    'form_name' => $formName,
                    'field_count' => $stats['total_fields'],
                    'mapping_percentage' => $stats['mapping_percentage'],
                    'document_type' => $this->inferDocumentType($formName)
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Load and cache CSV mappings
     */
    private function loadMappings(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            if (!file_exists($this->csvPath)) {
                Log::error('Canonical field mapping CSV not found', ['path' => $this->csvPath]);
                return collect([]);
            }

            return $this->parseCsv();
        });
    }

    /**
     * Parse CSV file into structured data
     */
    private function parseCsv(): Collection
    {
        $mappings = collect([]);
        
        if (($handle = fopen($this->csvPath, 'r')) !== false) {
            $headers = fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= count($headers)) {
                    $mapping = array_combine($headers, $data);
                    
                    // Clean and validate mapping data
                    $mapping = $this->cleanMappingData($mapping);
                    
                    $mappings->push($mapping);
                }
            }
            
            fclose($handle);
        }

        Log::info('Loaded canonical field mappings', [
            'total_mappings' => $mappings->count(),
            'unique_forms' => $mappings->pluck('form_name')->unique()->count()
        ]);

        return $mappings;
    }

    /**
     * Clean and validate mapping data
     */
    private function cleanMappingData(array $mapping): array
    {
        return [
            'form_name' => trim($mapping['form_name'] ?? ''),
            'field_label' => trim($mapping['field_label'] ?? ''),
            'field_key' => trim($mapping['field_key'] ?? ''),
            'canonical_key' => trim($mapping['canonical_key'] ?? ''),
            'mapping_status' => strtolower(trim($mapping['mapping_status'] ?? 'unmapped')),
            'type' => trim($mapping['type'] ?? 'text'),
            'required' => $this->parseBooleanValue($mapping['required'] ?? 'false'),
            'confidence' => (float) ($mapping['confidence'] ?? 0),
            'sample_value' => trim($mapping['sample_value'] ?? '')
        ];
    }

    /**
     * Organize mappings by field key for easy lookup
     */
    private function organizeMappings(Collection $mappings): array
    {
        $organized = [
            'form_name' => $mappings->first()['form_name'] ?? '',
            'field_mappings' => [],
            'canonical_keys' => [],
            'stats' => []
        ];

        foreach ($mappings as $mapping) {
            $fieldKey = $mapping['field_key'];
            $organized['field_mappings'][$fieldKey] = $mapping;
            
            if ($mapping['mapping_status'] === 'mapped') {
                $organized['canonical_keys'][$mapping['canonical_key']] = $fieldKey;
            }
        }

        return $organized;
    }

    /**
     * Normalize form name for consistent matching
     */
    private function normalizeFormName(string $formName, string $documentType = ''): string
    {
        $normalized = strtolower(trim($formName));
        $normalized = preg_replace('/[^a-z0-9_]/', '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized);
        $normalized = trim($normalized, '_');
        
        return $normalized;
    }

    /**
     * Find fuzzy match for field key
     */
    private function findFuzzyMatch(array $fieldMappings, string $fieldKey): ?array
    {
        $normalizedKey = strtolower($fieldKey);
        
        // Try partial matches
        foreach ($fieldMappings as $key => $mapping) {
            $normalizedMappingKey = strtolower($key);
            
            if (str_contains($normalizedMappingKey, $normalizedKey) || 
                str_contains($normalizedKey, $normalizedMappingKey)) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Check if mapping looks suspicious
     */
    private function isSuspiciousMapping(string $fieldKey, string $canonicalKey): bool
    {
        $fieldLower = strtolower($fieldKey);
        $canonicalLower = strtolower($canonicalKey);

        // Check for obvious mismatches
        $suspiciousPatterns = [
            ['phone', 'name'],
            ['address', 'email'],
            ['date', 'name'],
            ['number', 'name']
        ];

        foreach ($suspiciousPatterns as [$field, $canonical]) {
            if (str_contains($fieldLower, $field) && str_contains($canonicalLower, $canonical)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Suggest correction for suspicious mapping
     */
    private function suggestCorrection(string $fieldKey, string $canonicalKey): string
    {
        $fieldLower = strtolower($fieldKey);
        
        if (str_contains($fieldLower, 'phone')) {
            return 'contact_phone or patient_phone';
        }
        if (str_contains($fieldLower, 'email')) {
            return 'patient_email or contact_email';
        }
        if (str_contains($fieldLower, 'address')) {
            return 'patient_address or facility_address';
        }
        
        return 'review_mapping_manually';
    }

    /**
     * Parse boolean value from CSV
     */
    private function parseBooleanValue(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', '1', 'yes', 'on']);
    }

    /**
     * Infer document type from form name
     */
    private function inferDocumentType(string $formName): string
    {
        $formLower = strtolower($formName);
        
        if (str_contains($formLower, 'order')) {
            return 'OrderForm';
        }
        
        return 'IVR';
    }

    /**
     * Clear mapping cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->mappingCache = [];
    }

    /**
     * Refresh mappings from CSV
     */
    public function refreshMappings(): array
    {
        $this->clearCache();
        $mappings = $this->loadMappings();
        
        return [
            'total_mappings' => $mappings->count(),
            'unique_forms' => $mappings->pluck('form_name')->unique()->count(),
            'supported_forms' => $this->getSupportedForms()
        ];
    }
    /**
     * Inject Docuseal prefill data into mapped fields.
     *
     * This method sets default values for fields required by the Docuseal API.
     * It ensures that signature fields and image fields have appropriate defaults.
     * 
     * @param array $mappedData
     * @return array
     */
    public function injectDocusealPrefillData(array $mappedData): array {
        // Set default text for signature fields if not present
        if (!isset($mappedData['signature'])) {
            $mappedData['signature'] = 'Default Signature';
        }
        // Set a default placeholder for signature images if not present
        if (!isset($mappedData['signatureImage'])) {
            $mappedData['signatureImage'] = null; // Could be replaced with a default image URL or base64 string
        }
        return $mappedData;
    }

}

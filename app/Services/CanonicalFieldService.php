<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CanonicalFieldService
{
    private const CACHE_KEY = 'canonical-field-mappings';
    private array $mappings = [];

    public function __construct()
    {
        $this->mappings = $this->loadMappings();
    }

    private function loadMappings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $path = base_path('docs/data-and-reference/json-forms/unified-medical-form-mapping.json');

            if (!File::exists($path)) {
                Log::error('Unified form mapping file not found!', ['path' => $path]);
                return [];
            }

            try {
                $json = File::get($path);
                $data = json_decode($json, true);
                return $data['canonicalFieldDefinitions'] ?? [];
            } catch (\Exception $e) {
                Log::error('Failed to load or decode form mappings', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    public function getFieldValue(string $category, string $canonicalKey, array $formData): ?string
    {
        $definition = $this->mappings[$category][$canonicalKey] ?? null;

        if (!$definition) {
            return null;
        }

        // Check canonical key
        if (!empty($formData[$canonicalKey])) {
            return $formData[$canonicalKey];
        }

        // Check alternate keys
        foreach ($definition['alternateKeys'] ?? [] as $altKey) {
            if (!empty($formData[$altKey])) {
                return $formData[$altKey];
            }
        }

        // Check form implementations
        foreach ($definition['formImplementations'] ?? [] as $form) {
            if (!empty($formData[$form['fieldName']])) {
                return $formData[$form['fieldName']];
            }
        }

        return null;
    }

    public function applyFallbackDefaults(array $data): array
    {
        $defaults = [
            'surgical_global_period' => 'NO',
            'contact_person' => 'N/A',
            'additional_notes' => '',
            'secondary_insurance' => 'None',
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($data[$key]) || empty($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    public function refreshMappings(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->mappings = $this->loadMappings();
    }
    /**
     * Inject Docuseal prefill data into canonical fields.
     *
     * Applies default values for Docuseal API pre-fill requirements.
     * Ensures signature fields and image fields get appropriate defaults.
     *
     * @param array $fields
     * @return array
     */
    public function injectDocusealPrefillData(array $fields): array {
        // Set default text for signature fields if not present
        if (!isset($fields['signature'])) {
            $fields['signature'] = 'Default Signature';
        }
        // Set a default placeholder for signature images if not present
        if (!isset($fields['signatureImage'])) {
            $fields['signatureImage'] = null; // Could be replaced with a default image URL or base64 encoded string
        }
        return $fields;
    }

}


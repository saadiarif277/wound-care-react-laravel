<?php

namespace App\Services\AI;

use App\Models\PdfFieldMetadata;
use App\Models\PdfFieldNameIndex;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PdfFieldExtractorService
{
    private string $pythonScript;
    private string $tempPath;
    
    public function __construct()
    {
        $this->pythonScript = base_path('scripts/pdf_field_extractor.py');
        $this->tempPath = storage_path('app/temp/pdf_extraction');
        
        // Ensure temp directory exists
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }
    
    /**
     * Extract fields from all DocuSeal templates
     */
    public function extractAllTemplateFields(): array
    {
        $results = [];
        $templates = DocusealTemplate::with('manufacturer')->get();
        
        Log::info("Starting field extraction for {$templates->count()} templates");
        
        foreach ($templates as $template) {
            try {
                $result = $this->extractTemplateFields($template);
                $results[] = $result;
                
                Log::info("Extracted fields for template: {$template->template_name}", [
                    'template_id' => $template->docuseal_template_id,
                    'field_count' => $result['field_count'] ?? 0
                ]);
                
            } catch (\Exception $e) {
                Log::error("Failed to extract fields for template: {$template->template_name}", [
                    'template_id' => $template->docuseal_template_id,
                    'error' => $e->getMessage()
                ]);
                
                $results[] = [
                    'template_id' => $template->docuseal_template_id,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Extract fields from a specific DocuSeal template
     */
    public function extractTemplateFields(DocusealTemplate $template): array
    {
        try {
            // Get field metadata directly from DocuSeal API (no PDF download needed)
            $extractionResult = $this->extractFromDocusealAPI($template);
            
            // Store extracted fields in database
            $storedFields = $this->storeExtractedFields($extractionResult, $template);
            
            // Generate field name variants and AI suggestions
            $this->generateFieldVariants($storedFields);
            
            return [
                'template_id' => $template->docuseal_template_id,
                'template_name' => $template->template_name,
                'success' => true,
                'field_count' => count($storedFields),
                'stored_fields' => $storedFields->pluck('field_name')->toArray(),
                'extraction_metadata' => $extractionResult['metadata'] ?? []
            ];
            
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Extract field metadata directly from DocuSeal API
     */
    private function extractFromDocusealAPI(DocusealTemplate $template): array
    {
        try {
            $apiKey = config('services.docuseal.api_key');
            $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
            
            // Get template PDF download URL from DocuSeal API
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->get("{$apiUrl}/templates/{$template->docuseal_template_id}");
            
            if (!$response->successful()) {
                Log::warning("Failed to get template info from DocuSeal", [
                    'template_id' => $template->docuseal_template_id,
                    'status' => $response->status()
                ]);
                return null;
            }
            
            $templateData = $response->json();
            
            // Download the PDF document
            $pdfResponse = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->get("{$apiUrl}/templates/{$template->docuseal_template_id}/document");
            
            if (!$pdfResponse->successful()) {
                Log::warning("Failed to download PDF from DocuSeal", [
                    'template_id' => $template->docuseal_template_id,
                    'status' => $pdfResponse->status()
                ]);
                return null;
            }
            
            // Save PDF to temporary file
            $filename = "template_{$template->docuseal_template_id}_" . time() . ".pdf";
            $pdfPath = $this->tempPath . '/' . $filename;
            
            file_put_contents($pdfPath, $pdfResponse->body());
            
            return $pdfPath;
            
        } catch (\Exception $e) {
            Log::error("Error downloading PDF template", [
                'template_id' => $template->docuseal_template_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Run Python script to extract field metadata
     */
    private function runPythonExtraction(string $pdfPath, DocusealTemplate $template): array
    {
        // Create extraction configuration
        $config = [
            'pdf_path' => $pdfPath,
            'template_id' => $template->docuseal_template_id,
            'template_name' => $template->template_name,
            'manufacturer_name' => $template->manufacturer?->name,
            'extraction_methods' => ['pypdf2', 'pdfplumber', 'pymupdf'], // Try all methods
            'analyze_field_types' => true,
            'extract_coordinates' => true,
            'detect_medical_categories' => true
        ];
        
        $configPath = $this->tempPath . '/config_' . time() . '.json';
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
        
        try {
            // Run Python extraction script
            $command = "cd " . base_path('scripts') . " && ";
            $command .= "/bin/bash -c 'source ai_service_env/bin/activate && ";
            $command .= "python3 pdf_field_extractor.py \"{$configPath}\" 2>&1'";
            
            $output = shell_exec($command);
            
            // Clean up config file
            @unlink($configPath);
            
            if (empty($output)) {
                throw new \Exception("Python extraction script produced no output");
            }
            
            // Parse JSON result
            $result = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Failed to parse Python extraction output", [
                    'output' => $output,
                    'json_error' => json_last_error_msg()
                ]);
                throw new \Exception("Invalid JSON output from extraction script");
            }
            
            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Unknown extraction error');
            }
            
            return $result;
            
        } catch (\Exception $e) {
            @unlink($configPath);
            throw $e;
        }
    }
    
    /**
     * Store extracted field metadata in database
     */
    private function storeExtractedFields(array $extractionResult, DocusealTemplate $template): \Illuminate\Database\Eloquent\Collection
    {
        $storedFields = collect();
        $fields = $extractionResult['fields'] ?? [];
        
        foreach ($fields as $fieldData) {
            try {
                // Check if field already exists
                $existingField = PdfFieldMetadata::where('docuseal_template_id', $template->docuseal_template_id)
                    ->where('field_name', $fieldData['name'])
                    ->where('page_number', $fieldData['page'] ?? 1)
                    ->first();
                
                $fieldMetadata = [
                    'docuseal_template_id' => $template->docuseal_template_id,
                    'manufacturer_id' => $template->manufacturer_id,
                    'template_name' => $template->template_name,
                    'field_name' => $fieldData['name'],
                    'field_name_normalized' => $this->normalizeFieldName($fieldData['name']),
                    'field_type' => $this->detectFieldType($fieldData),
                    'field_subtype' => $fieldData['subtype'] ?? null,
                    'is_required' => $fieldData['required'] ?? false,
                    'is_readonly' => $fieldData['readonly'] ?? false,
                    'is_calculated' => $fieldData['calculated'] ?? false,
                    'field_validation' => $fieldData['validation'] ?? null,
                    'field_options' => $fieldData['options'] ?? null,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'max_length' => $fieldData['max_length'] ?? null,
                    'input_format' => $fieldData['format'] ?? null,
                    'page_number' => $fieldData['page'] ?? 1,
                    'x_coordinate' => $fieldData['x'] ?? null,
                    'y_coordinate' => $fieldData['y'] ?? null,
                    'width' => $fieldData['width'] ?? null,
                    'height' => $fieldData['height'] ?? null,
                    'tab_order' => $fieldData['tab_order'] ?? null,
                    'field_group' => $this->detectFieldGroup($fieldData['name']),
                    'medical_category' => $this->detectMedicalCategory($fieldData['name']),
                    'business_purpose' => $this->detectBusinessPurpose($fieldData['name']),
                    'field_description' => $fieldData['description'] ?? null,
                    'confidence_score' => $fieldData['confidence'] ?? 0.8,
                    'extraction_method' => $fieldData['extraction_method'] ?? 'pypdf2',
                    'extraction_version' => $fieldData['extraction_version'] ?? null,
                    'extraction_metadata' => $fieldData['metadata'] ?? [],
                    'extracted_at' => now(),
                ];
                
                if ($existingField) {
                    // Update existing field
                    $existingField->update($fieldMetadata);
                    $storedFields->push($existingField->refresh());
                } else {
                    // Create new field
                    $newField = PdfFieldMetadata::create($fieldMetadata);
                    $storedFields->push($newField);
                }
                
            } catch (\Exception $e) {
                Log::error("Failed to store field metadata", [
                    'field_name' => $fieldData['name'] ?? 'unknown',
                    'template_id' => $template->docuseal_template_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $storedFields;
    }
    
    /**
     * Generate field name variants and suggestions
     */
    private function generateFieldVariants(\Illuminate\Database\Eloquent\Collection $fields): void
    {
        foreach ($fields as $field) {
            $this->createFieldNameVariants($field);
        }
    }
    
    /**
     * Create field name variants for better matching
     */
    private function createFieldNameVariants(PdfFieldMetadata $field): void
    {
        $variants = [];
        $fieldName = $field->field_name;
        
        // Add exact match
        $variants[] = [
            'field_name_variant' => $fieldName,
            'canonical_field_name' => $fieldName,
            'variant_type' => 'exact',
            'similarity_score' => 1.0
        ];
        
        // Add normalized version
        $normalized = $field->field_name_normalized;
        if ($normalized !== strtolower($fieldName)) {
            $variants[] = [
                'field_name_variant' => $normalized,
                'canonical_field_name' => $fieldName,
                'variant_type' => 'normalized',
                'similarity_score' => 0.95
            ];
        }
        
        // Add common aliases
        $aliases = $this->generateFieldAliases($fieldName);
        foreach ($aliases as $alias => $confidence) {
            $variants[] = [
                'field_name_variant' => $alias,
                'canonical_field_name' => $fieldName,
                'variant_type' => 'alias',
                'similarity_score' => $confidence
            ];
        }
        
        // Add abbreviations
        $abbreviations = $this->generateAbbreviations($fieldName);
        foreach ($abbreviations as $abbrev => $confidence) {
            $variants[] = [
                'field_name_variant' => $abbrev,
                'canonical_field_name' => $fieldName,
                'variant_type' => 'abbreviation',
                'similarity_score' => $confidence
            ];
        }
        
        // Store variants in database
        foreach ($variants as $variantData) {
            PdfFieldNameIndex::firstOrCreate(
                [
                    'pdf_field_metadata_id' => $field->id,
                    'field_name_variant' => $variantData['field_name_variant']
                ],
                $variantData
            );
        }
    }
    
    /**
     * Utility methods
     */
    private function normalizeFieldName(string $fieldName): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $fieldName)));
    }
    
    private function detectFieldType(array $fieldData): string
    {
        $name = strtolower($fieldData['name'] ?? '');
        $type = strtolower($fieldData['type'] ?? '');
        
        // Email detection
        if (str_contains($name, 'email') || $type === 'email') {
            return 'email';
        }
        
        // Phone detection
        if (str_contains($name, 'phone') || str_contains($name, 'fax') || $type === 'phone') {
            return 'phone';
        }
        
        // Date detection
        if (str_contains($name, 'date') || str_contains($name, 'dob') || $type === 'date') {
            return 'date';
        }
        
        // Number detection
        if (str_contains($name, 'number') || str_contains($name, 'amount') || 
            str_contains($name, 'quantity') || $type === 'number') {
            return 'number';
        }
        
        // Checkbox detection
        if (str_contains($name, 'check') || str_contains($name, 'yes') || 
            str_contains($name, 'no') || $type === 'checkbox') {
            return 'checkbox';
        }
        
        // Signature detection
        if (str_contains($name, 'signature') || str_contains($name, 'sign') || $type === 'signature') {
            return 'signature';
        }
        
        // Default to text
        return 'text';
    }
    
    private function detectFieldGroup(string $fieldName): ?string
    {
        $name = strtolower($fieldName);
        
        if (str_contains($name, 'patient')) return 'patient_info';
        if (str_contains($name, 'provider') || str_contains($name, 'physician')) return 'provider_info';
        if (str_contains($name, 'facility') || str_contains($name, 'clinic')) return 'facility_info';
        if (str_contains($name, 'insurance') || str_contains($name, 'policy')) return 'insurance_info';
        if (str_contains($name, 'diagnosis') || str_contains($name, 'wound')) return 'clinical_info';
        if (str_contains($name, 'product') || str_contains($name, 'order')) return 'order_info';
        
        return null;
    }
    
    private function detectMedicalCategory(string $fieldName): ?string
    {
        $name = strtolower($fieldName);
        
        if (str_contains($name, 'patient')) return 'patient';
        if (str_contains($name, 'provider') || str_contains($name, 'physician') || str_contains($name, 'doctor')) return 'provider';
        if (str_contains($name, 'facility') || str_contains($name, 'clinic') || str_contains($name, 'hospital')) return 'facility';
        if (str_contains($name, 'insurance') || str_contains($name, 'policy') || str_contains($name, 'member')) return 'insurance';
        if (str_contains($name, 'diagnosis') || str_contains($name, 'wound') || str_contains($name, 'icd')) return 'clinical';
        if (str_contains($name, 'product') || str_contains($name, 'order') || str_contains($name, 'quantity')) return 'product';
        
        return null;
    }
    
    private function detectBusinessPurpose(string $fieldName): ?string
    {
        $name = strtolower($fieldName);
        
        if (str_contains($name, 'name') || str_contains($name, 'first') || str_contains($name, 'last')) return 'identification';
        if (str_contains($name, 'address') || str_contains($name, 'city') || str_contains($name, 'zip')) return 'contact_info';
        if (str_contains($name, 'phone') || str_contains($name, 'email') || str_contains($name, 'fax')) return 'communication';
        if (str_contains($name, 'date') || str_contains($name, 'dob') || str_contains($name, 'birth')) return 'temporal';
        if (str_contains($name, 'insurance') || str_contains($name, 'policy') || str_contains($name, 'member')) return 'billing';
        if (str_contains($name, 'signature') || str_contains($name, 'sign')) return 'authorization';
        if (str_contains($name, 'diagnosis') || str_contains($name, 'wound') || str_contains($name, 'icd')) return 'clinical_documentation';
        
        return null;
    }
    
    private function generateFieldAliases(string $fieldName): array
    {
        $aliases = [];
        $name = strtolower($fieldName);
        
        // Common alias patterns
        $aliasMap = [
            'patient name' => ['patient_name', 'patientname', 'pt_name', 'patient full name'],
            'provider name' => ['provider_name', 'physician_name', 'doctor_name', 'md_name'],
            'date of birth' => ['dob', 'birth_date', 'patient_dob', 'birthdate'],
            'phone number' => ['phone', 'telephone', 'tel', 'phone_number'],
            'email address' => ['email', 'e_mail', 'patient_email', 'email_address'],
            'insurance name' => ['insurance', 'payer', 'insurance_company', 'primary_insurance'],
            'policy number' => ['policy', 'member_id', 'insurance_id', 'policy_num'],
            'facility name' => ['facility', 'clinic', 'hospital', 'practice_name'],
        ];
        
        foreach ($aliasMap as $pattern => $variants) {
            if (str_contains($name, $pattern)) {
                foreach ($variants as $variant) {
                    $aliases[$variant] = 0.8;
                }
            }
        }
        
        return $aliases;
    }
    
    private function generateAbbreviations(string $fieldName): array
    {
        $abbreviations = [];
        $words = explode(' ', strtolower($fieldName));
        
        if (count($words) > 1) {
            // Create acronym
            $acronym = '';
            foreach ($words as $word) {
                if (!empty($word)) {
                    $acronym .= $word[0];
                }
            }
            if (strlen($acronym) > 1) {
                $abbreviations[$acronym] = 0.7;
            }
            
            // Create short versions
            $abbreviations[substr($fieldName, 0, 3)] = 0.6;
            $abbreviations[substr($fieldName, 0, 5)] = 0.7;
        }
        
        return $abbreviations;
    }
    
    /**
     * Get field statistics
     */
    public function getFieldStatistics(): array
    {
        return [
            'total_fields' => PdfFieldMetadata::count(),
            'verified_fields' => PdfFieldMetadata::where('extraction_verified', true)->count(),
            'high_confidence_fields' => PdfFieldMetadata::where('confidence_score', '>=', 0.8)->count(),
            'fields_by_type' => PdfFieldMetadata::groupBy('field_type')->selectRaw('field_type, count(*) as count')->get(),
            'fields_by_category' => PdfFieldMetadata::groupBy('medical_category')->selectRaw('medical_category, count(*) as count')->get(),
            'fields_by_manufacturer' => PdfFieldMetadata::with('manufacturer')
                ->get()
                ->groupBy('manufacturer.name')
                ->map(function($fields) { return $fields->count(); }),
            'extraction_methods' => PdfFieldMetadata::groupBy('extraction_method')->selectRaw('extraction_method, count(*) as count')->get(),
        ];
    }
    
    /**
     * Update SmartFieldMappingValidator with extracted field data
     */
    public function updateValidatorWithExtractedFields(): void
    {
        Cache::forget('valid_docuseal_fields');
        Log::info('Cleared field validation cache - will be refreshed with new extracted field data');
    }
} 
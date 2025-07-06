<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocusealService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DebugDocusealFields extends Command
{
    protected $signature = 'debug:docuseal-fields {template_id} {--test-mapping : Test field mapping with sample data}';
    protected $description = 'Debug DocuSeal template fields and field mapping';

    public function handle()
    {
        $templateId = $this->argument('template_id');
        $testMapping = $this->option('test-mapping');
        
        $this->info("🔍 Debugging DocuSeal Template: {$templateId}");
        
        try {
            // Get template fields from DocuSeal API
            $this->info("\n📋 Fetching template fields from DocuSeal API...");
            $fields = $this->getTemplateFields($templateId);
            
            if (empty($fields)) {
                $this->error("❌ No fields found for template {$templateId}");
                return 1;
            }
            
            $this->info("✅ Found " . count($fields) . " fields in template");
            
            // Display all field names
            $this->info("\n📝 Template Field Names:");
            foreach ($fields as $index => $field) {
                $name = $field['name'] ?? $field['key'] ?? "field_{$index}";
                $type = $field['type'] ?? 'unknown';
                $required = isset($field['required']) && $field['required'] ? ' (required)' : '';
                
                $this->line("  • {$name} [{$type}]{$required}");
            }
            
            if ($testMapping) {
                $this->testFieldMapping($templateId, $fields);
            }
            
            $this->info("\n🎯 Next Steps:");
            $this->line("1. Compare these field names with our mapping in DocusealService::transformQuickRequestData()");
            $this->line("2. Update field mappings to match exact field names");
            $this->line("3. Test with --test-mapping option");
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function getTemplateFields(string $templateId): array
    {
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
        
        if (!$apiKey) {
            throw new \Exception('DocuSeal API key not configured');
        }
        
        // Try different API endpoints that might return field information
        $endpoints = [
            "/templates/{$templateId}",
            "/templates/{$templateId}/fields",
            "/templates/{$templateId}/schema"
        ];
        
        foreach ($endpoints as $endpoint) {
            try {
                $this->line("  Trying endpoint: {$endpoint}");
                
                $response = Http::withHeaders([
                    'X-Auth-Token' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->get($apiUrl . $endpoint);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Look for fields in various locations in the response
                    $fields = $this->extractFields($data);
                    
                    if (!empty($fields)) {
                        $this->info("  ✅ Found fields in endpoint: {$endpoint}");
                        return $fields;
                    }
                }
            } catch (\Exception $e) {
                $this->line("  ❌ Failed: " . $e->getMessage());
            }
        }
        
        throw new \Exception('Could not retrieve template fields from any endpoint');
    }
    
    private function extractFields(array $data): array
    {
        // Look for fields in common locations in DocuSeal API responses
        if (isset($data['fields']) && is_array($data['fields'])) {
            return $data['fields'];
        }
        
        if (isset($data['schema']['fields']) && is_array($data['schema']['fields'])) {
            return $data['schema']['fields'];
        }
        
        if (isset($data['form_fields']) && is_array($data['form_fields'])) {
            return $data['form_fields'];
        }
        
        if (isset($data['documents']) && is_array($data['documents'])) {
            $fields = [];
            foreach ($data['documents'] as $document) {
                if (isset($document['fields']) && is_array($document['fields'])) {
                    $fields = array_merge($fields, $document['fields']);
                }
            }
            return $fields;
        }
        
        // Log the structure we got to help debug
        Log::info('DocuSeal API response structure', [
            'keys' => array_keys($data),
            'sample_data' => array_slice($data, 0, 3, true)
        ]);
        
        return [];
    }
    
    private function testFieldMapping(string $templateId, array $templateFields): void
    {
        $this->info("\n🧪 Testing Field Mapping...");
        
        // Sample data that would come from the frontend
        $sampleData = [
            'patient_name' => 'John Smith',
            'patient_first_name' => 'John',
            'patient_last_name' => 'Smith',
            'patient_dob' => '1985-03-15',
            'provider_name' => 'Dr. Jane Wilson',
            'provider_npi' => '1234567890',
            'facility_name' => 'MSC Wound Care',
            'primary_insurance_name' => 'Blue Cross Blue Shield',
            'primary_member_id' => 'ABC123456789',
            'wound_location' => 'right_foot',
            'wound_type' => 'diabetic_ulcer',
            'product_name' => 'Amnio AMP'
        ];
        
        // Get our current field mappings
        $docusealService = app(DocusealService::class);
        $reflectionClass = new \ReflectionClass($docusealService);
        $method = $reflectionClass->getMethod('transformQuickRequestData');
        $method->setAccessible(true);
        
        $mappedFields = $method->invoke($docusealService, $sampleData, $templateId);
        
        $this->info("\n📊 Current Mapping Results:");
        foreach ($mappedFields as $fieldName => $value) {
            $this->line("  • '{$fieldName}' => '{$value}'");
        }
        
        // Check which fields match template fields
        $templateFieldNames = array_column($templateFields, 'name');
        $matchingFields = [];
        $unmatchedFields = [];
        
        foreach ($mappedFields as $fieldName => $value) {
            if (in_array($fieldName, $templateFieldNames)) {
                $matchingFields[] = $fieldName;
            } else {
                $unmatchedFields[] = $fieldName;
            }
        }
        
        $this->info("\n✅ Matching Fields (" . count($matchingFields) . "):");
        foreach ($matchingFields as $field) {
            $this->line("  • {$field}");
        }
        
        $this->warn("\n❌ Unmatched Fields (" . count($unmatchedFields) . "):");
        foreach ($unmatchedFields as $field) {
            $this->line("  • {$field}");
        }
        
        // Suggest corrections
        $this->info("\n💡 Suggested Field Name Corrections:");
        foreach ($unmatchedFields as $unmatchedField) {
            $suggestion = $this->findClosestMatch($unmatchedField, $templateFieldNames);
            if ($suggestion) {
                $this->line("  • '{$unmatchedField}' → '{$suggestion}'");
            }
        }
    }
    
    private function findClosestMatch(string $needle, array $haystack): ?string
    {
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($haystack as $option) {
            $score = similar_text(strtolower($needle), strtolower($option));
            if ($score > $bestScore && $score > strlen($needle) * 0.6) {
                $bestScore = $score;
                $bestMatch = $option;
            }
        }
        
        return $bestMatch;
    }
} 
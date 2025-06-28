<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InspectDocuSealTemplate extends Command
{
    protected $signature = 'docuseal:inspect-template {template_id}';
    protected $description = 'Inspect a DocuSeal template to see all available fields';

    public function handle()
    {
        $templateId = $this->argument('template_id');
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
        
        if (!$apiKey) {
            $this->error('DocuSeal API key not configured');
            return 1;
        }
        
        $this->info("Inspecting DocuSeal template: $templateId");
        
        try {
            // Get template from API
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->get("{$apiUrl}/templates/{$templateId}");
            
            if (!$response->successful()) {
                $this->error("Failed to get template: " . $response->body());
                return 1;
            }
            
            $template = $response->json();
            
            $this->info("\nTemplate Details:");
            $this->info("Name: " . ($template['name'] ?? 'Unknown'));
            $this->info("ID: " . ($template['id'] ?? $templateId));
            $this->info("Folder ID: " . ($template['folder_id'] ?? 'None'));
            
            // Extract all fields
            $allFields = [];
            
            if (isset($template['documents']) && is_array($template['documents'])) {
                foreach ($template['documents'] as $docIndex => $document) {
                    if (isset($document['fields']) && is_array($document['fields'])) {
                        foreach ($document['fields'] as $field) {
                            $fieldName = $field['name'] ?? '';
                            if (!empty($fieldName)) {
                                $allFields[$fieldName] = [
                                    'name' => $fieldName,
                                    'type' => $field['type'] ?? 'text',
                                    'required' => $field['required'] ?? false,
                                    'title' => $field['title'] ?? $fieldName,
                                    'document_index' => $docIndex
                                ];
                            }
                        }
                    }
                }
            }
            
            // Also check schema if available
            if (isset($template['schema']) && is_array($template['schema'])) {
                foreach ($template['schema'] as $schemaField) {
                    $fieldName = $schemaField['name'] ?? '';
                    if (!empty($fieldName) && !isset($allFields[$fieldName])) {
                        $allFields[$fieldName] = [
                            'name' => $fieldName,
                            'type' => $schemaField['type'] ?? 'text',
                            'required' => $schemaField['required'] ?? false,
                            'title' => $schemaField['title'] ?? $fieldName,
                            'from_schema' => true
                        ];
                    }
                }
            }
            
            // Also check fields array if available
            if (isset($template['fields']) && is_array($template['fields'])) {
                foreach ($template['fields'] as $field) {
                    $fieldName = $field['name'] ?? '';
                    if (!empty($fieldName) && !isset($allFields[$fieldName])) {
                        $allFields[$fieldName] = [
                            'name' => $fieldName,
                            'type' => $field['type'] ?? 'text',
                            'required' => $field['required'] ?? false,
                            'title' => $field['title'] ?? $fieldName,
                            'from_fields' => true
                        ];
                    }
                }
            }
            
            $this->info("\nTotal fields found: " . count($allFields));
            
            // Group fields by keyword
            $providerFields = [];
            $patientFields = [];
            $insuranceFields = [];
            $clinicalFields = [];
            $otherFields = [];
            
            foreach ($allFields as $fieldName => $fieldInfo) {
                $lowerName = strtolower($fieldName);
                
                if (strpos($lowerName, 'provider') !== false || 
                    strpos($lowerName, 'physician') !== false || 
                    strpos($lowerName, 'npi') !== false) {
                    $providerFields[] = $fieldInfo;
                } elseif (strpos($lowerName, 'patient') !== false || 
                         strpos($lowerName, 'dob') !== false) {
                    $patientFields[] = $fieldInfo;
                } elseif (strpos($lowerName, 'insurance') !== false || 
                         strpos($lowerName, 'member') !== false || 
                         strpos($lowerName, 'policy') !== false) {
                    $insuranceFields[] = $fieldInfo;
                } elseif (strpos($lowerName, 'wound') !== false || 
                         strpos($lowerName, 'diagnosis') !== false || 
                         strpos($lowerName, 'clinical') !== false) {
                    $clinicalFields[] = $fieldInfo;
                } else {
                    $otherFields[] = $fieldInfo;
                }
            }
            
            // Display grouped fields
            if (!empty($providerFields)) {
                $this->info("\nProvider/Physician Fields:");
                $this->table(['Field Name', 'Type', 'Required'], array_map(function($f) {
                    return [$f['name'], $f['type'], $f['required'] ? 'Yes' : 'No'];
                }, $providerFields));
            }
            
            if (!empty($patientFields)) {
                $this->info("\nPatient Fields:");
                $this->table(['Field Name', 'Type', 'Required'], array_map(function($f) {
                    return [$f['name'], $f['type'], $f['required'] ? 'Yes' : 'No'];
                }, $patientFields));
            }
            
            if (!empty($insuranceFields)) {
                $this->info("\nInsurance Fields:");
                $this->table(['Field Name', 'Type', 'Required'], array_map(function($f) {
                    return [$f['name'], $f['type'], $f['required'] ? 'Yes' : 'No'];
                }, $insuranceFields));
            }
            
            if (!empty($clinicalFields)) {
                $this->info("\nClinical Fields:");
                $this->table(['Field Name', 'Type', 'Required'], array_map(function($f) {
                    return [$f['name'], $f['type'], $f['required'] ? 'Yes' : 'No'];
                }, $clinicalFields));
            }
            
            if (!empty($otherFields)) {
                $this->info("\nOther Fields:");
                $this->table(['Field Name', 'Type', 'Required'], array_map(function($f) {
                    return [$f['name'], $f['type'], $f['required'] ? 'Yes' : 'No'];
                }, array_slice($otherFields, 0, 20))); // Limit to first 20
                
                if (count($otherFields) > 20) {
                    $this->info("... and " . (count($otherFields) - 20) . " more fields");
                }
            }
            
            // Save to file for reference
            $outputFile = storage_path("app/docuseal_template_{$templateId}_fields.json");
            file_put_contents($outputFile, json_encode($allFields, JSON_PRETTY_PRINT));
            $this->info("\nField list saved to: $outputFile");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}
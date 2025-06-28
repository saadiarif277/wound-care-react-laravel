<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Docuseal\DocusealTemplate;

class InspectDocuSealTemplateFields extends Command
{
    protected $signature = 'docuseal:inspect-template {manufacturer}';
    protected $description = 'Inspect exact field names in a DocuSeal template';

    public function handle()
    {
        $manufacturerName = $this->argument('manufacturer');
        
        // Find template
        $template = DocusealTemplate::whereHas('manufacturer', function($q) use ($manufacturerName) {
            $q->where('name', 'LIKE', "%{$manufacturerName}%");
        })->where('document_type', 'IVR')->first();
        
        if (!$template) {
            $this->error("No IVR template found for manufacturer: {$manufacturerName}");
            return 1;
        }
        
        $this->info("Template: {$template->template_name} (ID: {$template->docuseal_template_id})");
        $this->info("Fetching template fields from DocuSeal API...\n");
        
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
        
        try {
            // Get template details
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
                'Content-Type' => 'application/json',
            ])->get("{$apiUrl}/templates/{$template->docuseal_template_id}");
            
            if (!$response->successful()) {
                $this->error("Failed to fetch template: " . $response->body());
                return 1;
            }
            
            $templateData = $response->json();
            
            // Extract all field names
            $allFields = [];
            if (isset($templateData['fields']) && is_array($templateData['fields'])) {
                foreach ($templateData['fields'] as $field) {
                    $allFields[] = [
                        'name' => $field['name'] ?? 'Unknown',
                        'type' => $field['type'] ?? 'text',
                        'required' => $field['required'] ?? false,
                        'submitter' => $field['submitter_uuid'] ?? 'default'
                    ];
                }
            }
            
            // Also check documents for fields
            if (isset($templateData['documents']) && is_array($templateData['documents'])) {
                foreach ($templateData['documents'] as $document) {
                    if (isset($document['fields']) && is_array($document['fields'])) {
                        foreach ($document['fields'] as $field) {
                            $allFields[] = [
                                'name' => $field['name'] ?? 'Unknown',
                                'type' => $field['type'] ?? 'text',
                                'required' => $field['required'] ?? false,
                                'submitter' => $field['submitter_uuid'] ?? 'default'
                            ];
                        }
                    }
                }
            }
            
            // Also check schema if available
            if (isset($templateData['schema']) && is_array($templateData['schema'])) {
                foreach ($templateData['schema'] as $schemaItem) {
                    if (isset($schemaItem['fields']) && is_array($schemaItem['fields'])) {
                        foreach ($schemaItem['fields'] as $field) {
                            $allFields[] = [
                                'name' => $field['name'] ?? 'Unknown',
                                'type' => $field['type'] ?? 'text',
                                'required' => $field['required'] ?? false,
                                'submitter' => 'schema'
                            ];
                        }
                    }
                }
            }
            
            // Remove duplicates
            $uniqueFields = [];
            $seen = [];
            foreach ($allFields as $field) {
                if (!in_array($field['name'], $seen)) {
                    $uniqueFields[] = $field;
                    $seen[] = $field['name'];
                }
            }
            
            $this->info("Found " . count($uniqueFields) . " unique fields:");
            $this->table(['Field Name', 'Type', 'Required', 'Source'], $uniqueFields);
            
            // Show exact field names for copying
            $this->info("\nExact field names (for reference):");
            foreach ($uniqueFields as $field) {
                $this->line("  \"{$field['name']}\"");
            }
            
            // Check submitters/roles
            if (isset($templateData['submitters']) && is_array($templateData['submitters'])) {
                $this->info("\nSubmitter Roles:");
                foreach ($templateData['submitters'] as $submitter) {
                    $this->line("  - " . ($submitter['name'] ?? 'Unknown') . " (UUID: " . ($submitter['uuid'] ?? 'N/A') . ")");
                }
            }
            
            // Show full template structure for debugging
            $this->info("\nFull template structure keys:");
            $this->line("  " . implode(", ", array_keys($templateData)));
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
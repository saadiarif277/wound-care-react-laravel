<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client as HttpClient;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\Log;

class SyncDocuSealTemplates extends Command
{
    protected $signature = 'docuseal:sync-templates {--test : Test mode to see what would be synced}';
    protected $description = 'Sync templates from DocuSeal including nested folders';

    public function handle()
    {
        $isTest = $this->option('test');
        
        $docusealApiKey = config('services.docuseal.api_key');
        $docusealApiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
        
        if (!$docusealApiKey) {
            $this->error('DocuSeal API key not configured. Please set DOCUSEAL_API_KEY in your environment.');
            return 1;
        }

        $this->info('🔄 Starting DocuSeal template sync...');
        $this->info("API URL: {$docusealApiUrl}");
        
        $client = new HttpClient();
        
        try {
            // Fetch all templates
            $response = $client->get($docusealApiUrl . '/templates', [
                'headers' => [
                    'X-Auth-Token' => $docusealApiKey,
                ],
                'timeout' => 30,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (!is_array($responseData)) {
                $this->error('Invalid response from DocuSeal API');
                return 1;
            }
            
            // Check if this is a paginated response
            $templates = [];
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                // Paginated response
                $templates = $responseData['data'];
                $this->line("Response type: Paginated");
                $this->line("Total count: " . ($responseData['count'] ?? 'unknown'));
            } elseif (isset($responseData[0]) && is_array($responseData[0])) {
                // Direct array response
                $templates = $responseData;
                $this->line("Response type: Direct array");
            } else {
                // Try to detect what we got
                $this->error("Unexpected response structure. Keys found: " . implode(', ', array_keys($responseData)));
                $this->line("Raw response (first 500 chars): " . substr(json_encode($responseData), 0, 500));
                return 1;
            }

            $templateCount = count($templates);
            $this->info("✅ Found {$templateCount} templates in current page");
            
            // Group by folder
            $folderGroups = [];
            $noFolder = [];
            
            foreach ($templates as $template) {
                $this->line('');
                $templateName = $template['name'] ?? 'Unnamed Template';
                $templateId = $template['id'] ?? 'unknown';
                $this->info("Template: {$templateName}");
                $this->line("  ID: {$templateId}");
                
                // Show all available keys
                if (is_array($template)) {
                    $this->line("  Available fields: " . implode(', ', array_keys($template)));
                }
                
                // Check for folder info
                $folderName = null;
                if (isset($template['folder'])) {
                    if (is_array($template['folder'])) {
                        $folderName = $template['folder']['name'] ?? null;
                        $this->line("  Folder (object): {$folderName}");
                    } else {
                        $folderName = $template['folder'];
                        $this->line("  Folder (string): {$folderName}");
                    }
                }
                
                // Check other potential folder fields
                if (isset($template['folder_name'])) {
                    $this->line("  Folder Name: {$template['folder_name']}");
                    $folderName = $folderName ?? $template['folder_name'];
                }
                
                if (isset($template['path'])) {
                    $this->line("  Path: {$template['path']}");
                }
                
                if ($folderName) {
                    $folderGroups[$folderName][] = $template;
                } else {
                    $noFolder[] = $template;
                }
                
                // Try to detect manufacturer
                $manufacturer = $this->detectManufacturer($folderName, $template);
                if ($manufacturer) {
                    $this->line("  <fg=green>✓</> Matched to manufacturer: {$manufacturer->name} (ID: {$manufacturer->id})");
                } else {
                    $this->line("  <fg=yellow>⚠</> No manufacturer match found");
                }
                
                if (!$isTest) {
                    // Actually sync the template
                    $this->syncTemplate($template, $manufacturer);
                }
            }
            
            // Summary
            $this->line('');
            $this->info('📊 Summary:');
            $this->line('Folders found: ' . count($folderGroups));
            foreach ($folderGroups as $folder => $templates) {
                $this->line("  - {$folder}: " . count($templates) . " templates");
            }
            
            if (count($noFolder) > 0) {
                $this->line("  - (No folder): " . count($noFolder) . " templates");
            }
            
            if ($isTest) {
                $this->info('');
                $this->info('This was a test run. Use --test=false to actually sync templates.');
            }
            
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            Log::error('DocuSeal sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
        
        return 0;
    }
    
    private function detectManufacturer(?string $folderName, array $templateData): ?Manufacturer
    {
        if (!$folderName && isset($templateData['name'])) {
            // Try to extract from template name
            if (preg_match('/^([^-]+)\s*-/', $templateData['name'], $matches)) {
                $folderName = trim($matches[1]);
            }
        }
        
        if (!$folderName) {
            return null;
        }
        
        // Try exact match first
        $manufacturer = Manufacturer::where('name', $folderName)->first();
        if ($manufacturer) {
            return $manufacturer;
        }
        
        // Try case-insensitive match
        $manufacturer = Manufacturer::whereRaw('LOWER(name) = ?', [strtolower($folderName)])->first();
        if ($manufacturer) {
            return $manufacturer;
        }
        
        // Try partial match
        $manufacturer = Manufacturer::where('name', 'LIKE', '%' . $folderName . '%')->first();
        if ($manufacturer) {
            return $manufacturer;
        }
        
        // Try common variations
        $variations = [
            'MedLife' => ['MedLife Solutions', 'MedLife', 'Med Life'],
            'ACZ' => ['ACZ', 'ACZ Laboratories'],
            'Extremity Care' => ['Extremity Care', 'ExtremityCare'],
            'BioWound' => ['BioWound', 'Bio Wound'],
            'ImbedBio' => ['ImbedBio', 'Imbed Bio'],
        ];
        
        foreach ($variations as $key => $names) {
            if (stripos($folderName, $key) !== false) {
                $manufacturer = Manufacturer::whereIn('name', $names)->first();
                if ($manufacturer) {
                    return $manufacturer;
                }
            }
        }
        
        return null;
    }
    
    private function syncTemplate(array $docusealTemplate, ?Manufacturer $manufacturer): void
    {
        $localTemplate = DocusealTemplate::where('docuseal_template_id', $docusealTemplate['id'])->first();
        
        if (!$localTemplate) {
            $this->line("    Creating new template record...");
            DocusealTemplate::create([
                'template_name' => $docusealTemplate['name'] ?? 'Unnamed Template',
                'docuseal_template_id' => $docusealTemplate['id'],
                'document_type' => $this->detectDocumentType($docusealTemplate['name'] ?? ''),
                'manufacturer_id' => $manufacturer?->id,
                'is_active' => true,
                'is_default' => false,
                'field_mappings' => [],
                'extraction_metadata' => [
                    'synced_from_docuseal' => true,
                    'sync_date' => now()->toIso8601String(),
                    'docuseal_data' => $docusealTemplate,
                ],
            ]);
        } else {
            $this->line("    Template already exists, updating metadata...");
            $localTemplate->update([
                'manufacturer_id' => $manufacturer?->id ?? $localTemplate->manufacturer_id,
                'extraction_metadata' => array_merge($localTemplate->extraction_metadata ?? [], [
                    'last_sync' => now()->toIso8601String(),
                    'docuseal_data' => $docusealTemplate,
                ]),
            ]);
        }
    }
    
    private function detectDocumentType(string $templateName): string
    {
        $lowercaseName = strtolower($templateName);
        
        if (str_contains($lowercaseName, 'ivr') || str_contains($lowercaseName, 'insurance verification')) {
            return 'IVR';
        } elseif (str_contains($lowercaseName, 'order') || str_contains($lowercaseName, 'purchase')) {
            return 'OrderForm';
        } elseif (str_contains($lowercaseName, 'onboard')) {
            return 'OnboardingForm';
        } elseif (str_contains($lowercaseName, 'insurance')) {
            return 'InsuranceVerification';
        }
        
        return 'IVR'; // Default
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocusealService;
use App\Services\AzureDocumentIntelligenceService;
use App\Services\TemplateIntelligenceService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;
use App\Jobs\SyncDocuSealTemplateJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncDocuSealTemplatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'docuseal:sync-templates 
                            {--force : Force sync even if templates exist}
                            {--manufacturer= : Sync templates for specific manufacturer only}
                            {--queue : Process templates via queue (recommended for large numbers)}';

    /**
     * The console command description.
     */
    protected $description = 'Sync all DocuSeal templates and their field mappings to database';

    private DocusealService $docusealService;
    private TemplateIntelligenceService $templateIntelligence;

    public function __construct(
        DocusealService $docusealService,
        TemplateIntelligenceService $templateIntelligence
    ) {
        parent::__construct();
        $this->docusealService = $docusealService;
        $this->templateIntelligence = $templateIntelligence;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting DocuSeal Template Sync...');

        // Test connection first
        $connectionTest = $this->docusealService->testConnection();
        if (!$connectionTest['success']) {
            $this->error('❌ DocuSeal API connection failed: ' . $connectionTest['error']);
            return self::FAILURE;
        }

        $this->info('✅ DocuSeal API connection successful');
        $this->info('📊 Found ' . $connectionTest['templates_count'] . ' templates in DocuSeal');

        try {
            // Fetch all templates from DocuSeal API
            $templates = $this->fetchAllTemplates();
            
            if (empty($templates)) {
                $this->error('❌ No templates found in DocuSeal');
                return self::FAILURE;
            }

            $this->info("📋 Processing " . count($templates) . " templates...");

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($templates as $template) {
                try {
                    $result = $this->processTemplate($template);
                    
                    if ($result['action'] === 'processed') {
                        $processedCount++;
                        $this->line("✅ {$result['name']} - {$result['message']}");
                    } elseif ($result['action'] === 'skipped') {
                        $skippedCount++;
                        $this->line("⏭️  {$result['name']} - {$result['message']}");
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $templateName = $template['name'] ?? 'Unknown';
                    $this->error("❌ Error processing template {$templateName}: {$e->getMessage()}");
                    Log::error('DocuSeal template sync error', [
                        'template' => $template,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Display summary
            $this->newLine();
            $this->info('📈 Sync Summary:');
            $this->table(['Status', 'Count'], [
                ['Processed', $processedCount],
                ['Skipped', $skippedCount],
                ['Errors', $errorCount],
                ['Total', count($templates)]
            ]);

            if ($errorCount > 0) {
                $this->warn('⚠️  Some templates had errors. Check logs for details.');
                return self::FAILURE;
            }

            $this->info('🎉 DocuSeal template sync completed successfully!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Template sync failed: ' . $e->getMessage());
            Log::error('DocuSeal template sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Fetch all templates from DocuSeal API (including folder-organized templates)
     */
    private function fetchAllTemplates(): array
    {
        $this->info('🔍 Fetching templates from DocuSeal API...');
        $allTemplates = [];

        try {
            // First, fetch top-level templates
            $this->info('📁 Fetching top-level templates...');
            $topLevelTemplates = $this->fetchTopLevelTemplates();
            $allTemplates = array_merge($allTemplates, $topLevelTemplates);
            
            if (!empty($topLevelTemplates)) {
                $this->info("✅ Found " . count($topLevelTemplates) . " top-level templates");
            }

            // Then, fetch all folders and their templates
            $this->info('📂 Fetching folders and folder-based templates...');
            $folders = $this->fetchFolders();
            
            if (!empty($folders)) {
                $this->info("📂 Found " . count($folders) . " folders");
                
                foreach ($folders as $folder) {
                    $folderName = $folder['name'] ?? 'Unknown Folder';
                    $folderId = $folder['id'] ?? null;
                    
                    if ($folderId) {
                        $this->line("  📁 Processing folder: {$folderName}");
                        $folderTemplates = $this->fetchFolderTemplates($folderId, $folderName);
                        
                        if (!empty($folderTemplates)) {
                            $this->line("    ✅ Found " . count($folderTemplates) . " templates in {$folderName}");
                            $allTemplates = array_merge($allTemplates, $folderTemplates);
                        } else {
                            $this->line("    ⚪ No templates in {$folderName}");
                        }
                    }
                }
            } else {
                $this->warn('⚠️  No folders found - only processing top-level templates');
            }

            // Remove duplicates (in case a template appears both at top level and in folder)
            $uniqueTemplates = $this->removeDuplicateTemplates($allTemplates);
            
            $this->info("🎯 Total unique templates found: " . count($uniqueTemplates));
            
            return $uniqueTemplates;

        } catch (\Exception $e) {
            throw new \Exception('Failed to fetch templates: ' . $e->getMessage());
        }
    }

    /**
     * Fetch top-level templates
     */
    private function fetchTopLevelTemplates(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => config('docuseal.api_key'),
            ])->get(config('docuseal.api_url') . '/templates');

            if (!$response->successful()) {
                throw new \Exception('Top-level templates API request failed: ' . $response->body());
            }

            $templates = $response->json();
            
            if (!is_array($templates)) {
                throw new \Exception('Invalid response format for top-level templates');
            }

            // Add metadata to indicate these are top-level
            foreach ($templates as &$template) {
                $template['_folder_info'] = [
                    'is_top_level' => true,
                    'folder_name' => null,
                    'folder_id' => null
                ];
            }

            return $templates;

        } catch (\Exception $e) {
            Log::warning('Could not fetch top-level templates', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fetch all folders
     */
    private function fetchFolders(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => config('docuseal.api_key'),
            ])->get(config('docuseal.api_url') . '/folders');

            if (!$response->successful()) {
                throw new \Exception('Folders API request failed: ' . $response->body());
            }

            $folders = $response->json();
            
            if (!is_array($folders)) {
                throw new \Exception('Invalid response format for folders');
            }

            return $folders;

        } catch (\Exception $e) {
            Log::warning('Could not fetch folders', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fetch templates from a specific folder
     */
    private function fetchFolderTemplates(string $folderId, string $folderName): array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => config('docuseal.api_key'),
            ])->get(config('docuseal.api_url') . "/folders/{$folderId}/templates");

            if (!$response->successful()) {
                Log::warning("Failed to fetch templates from folder {$folderName}", [
                    'folder_id' => $folderId,
                    'error' => $response->body()
                ]);
                return [];
            }

            $templates = $response->json();
            
            if (!is_array($templates)) {
                Log::warning("Invalid response format for folder {$folderName} templates");
                return [];
            }

            // Add folder metadata to each template
            foreach ($templates as &$template) {
                $template['_folder_info'] = [
                    'is_top_level' => false,
                    'folder_name' => $folderName,
                    'folder_id' => $folderId
                ];
            }

            return $templates;

        } catch (\Exception $e) {
            Log::warning("Could not fetch templates from folder {$folderName}", [
                'folder_id' => $folderId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Remove duplicate templates (same ID from different sources)
     */
    private function removeDuplicateTemplates(array $templates): array
    {
        $uniqueTemplates = [];
        $seenIds = [];

        foreach ($templates as $template) {
            $templateId = $template['id'] ?? null;
            
            if ($templateId && !in_array($templateId, $seenIds)) {
                $seenIds[] = $templateId;
                $uniqueTemplates[] = $template;
            }
        }

        $duplicateCount = count($templates) - count($uniqueTemplates);
        if ($duplicateCount > 0) {
            $this->line("🔄 Removed {$duplicateCount} duplicate templates");
        }

        return $uniqueTemplates;
    }

    /**
     * Process individual template
     */
    private function processTemplate(array $templateData): array
    {
        $templateId = $templateData['id'] ?? null;
        $templateName = $templateData['name'] ?? 'Unknown Template';

        if (!$templateId) {
            throw new \Exception('Template missing ID');
        }

        // Check if template already exists (unless force option is used)
        $existingTemplate = DocusealTemplate::where('docuseal_template_id', $templateId)->first();
        
        if ($existingTemplate && !$this->option('force')) {
            return [
                'action' => 'skipped',
                'name' => $templateName,
                'message' => 'Already exists (use --force to update)'
            ];
        }

        // Fetch detailed template information including fields
        $detailedTemplate = $this->fetchTemplateDetails($templateId);

        // Use intelligent analysis to determine manufacturer, document type, and field mappings
        $this->line("  🧠 Analyzing template with AI...");
        $analysis = $this->templateIntelligence->analyzeTemplate($templateData, $detailedTemplate);
        
        $manufacturer = $analysis['manufacturer'];
        $documentType = $analysis['document_type'];
        $confidenceScore = $analysis['confidence_score'];
        $analysisMethod = implode(', ', $analysis['analysis_methods']);
        
        $this->line("    📊 Analysis: {$confidenceScore}% confidence via {$analysisMethod}");
        $this->line("    🏭 Manufacturer: " . ($manufacturer?->name ?? 'Unknown'));
        $this->line("    📋 Document Type: {$documentType}");

        if ($this->option('queue')) {
            // Dispatch to queue for processing
            SyncDocuSealTemplateJob::dispatch($templateData, $detailedTemplate, $manufacturer, $documentType);
            
            return [
                'action' => 'processed',
                'name' => $templateName,
                'message' => 'Queued for processing'
            ];
        } else {
            // Process immediately
            $this->syncTemplateToDatabase($templateData, $detailedTemplate, $manufacturer, $documentType);
            
            return [
                'action' => 'processed',
                'name' => $templateName,
                'message' => 'Synced successfully'
            ];
        }
    }

    /**
     * Fetch detailed template information including fields
     */
    private function fetchTemplateDetails(string $templateId): array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => config('docuseal.api_key'),
            ])->get(config('docuseal.api_url') . "/templates/{$templateId}");

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch template details: ' . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::warning('Could not fetch detailed template info', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            
            // Return basic structure if detailed fetch fails
            return [
                'fields' => [],
                'schema' => []
            ];
        }
    }

    /**
     * Determine manufacturer from folder name or template name
     */
    private function determineManufacturer(string $templateName, array $templateData): ?Manufacturer
    {
        // First, try to determine from folder name (primary method)
        $folderInfo = $templateData['_folder_info'] ?? null;
        if ($folderInfo && !$folderInfo['is_top_level']) {
            $folderName = $folderInfo['folder_name'];
            $manufacturer = $this->determineManufacturerFromFolderName($folderName);
            if ($manufacturer) {
                return $manufacturer;
            }
        }

        // Fallback to template name patterns
        return $this->determineManufacturerFromTemplateName($templateName);
    }

    /**
     * Determine manufacturer from folder name (primary method)
     */
    private function determineManufacturerFromFolderName(string $folderName): ?Manufacturer
    {
        // Direct folder name to manufacturer mappings
        $folderMappings = [
            'ACZ' => 'ACZ',
            'Advanced Health (Complete AA)' => 'Advanced Health',
            'Advanced Health' => 'Advanced Health',
            'Amnio Amp-MSC BAA' => 'MiMedx',
            'AmnioBand' => 'AmnioBand',
            'BioWerX' => 'BioWerX',
            'BioWound Onboarding' => 'BioWound',
            'BioWound' => 'BioWound',
            'Extremity Care Onboarding' => 'Extremity Care',
            'Extremity Care' => 'Extremity Care',
            'MSC Forms' => 'MSC',
            'SKYE Onboarding' => 'SKYE',
            'SKYE' => 'SKYE',
            'Total Ancillary Forms' => 'Total Ancillary',
            'Integra' => 'Integra',
            'Kerecis' => 'Kerecis',
            'MiMedx' => 'MiMedx',
            'Organogenesis' => 'Organogenesis',
            'Smith & Nephew' => 'Smith & Nephew',
            'StimLabs' => 'StimLabs',
            'Tissue Tech' => 'Tissue Tech'
        ];

        // Try exact match first
        if (isset($folderMappings[$folderName])) {
            $manufacturerName = $folderMappings[$folderName];
            return Manufacturer::firstOrCreate(
                ['name' => $manufacturerName],
                [
                    'is_active' => true,
                    'contact_email' => config("manufacturers.email_recipients.{$manufacturerName}.0")
                ]
            );
        }

        // Try fuzzy matching for folder names
        $folderNameLower = strtolower($folderName);
        foreach ($folderMappings as $pattern => $manufacturerName) {
            if (strpos($folderNameLower, strtolower($pattern)) !== false) {
                return Manufacturer::firstOrCreate(
                    ['name' => $manufacturerName],
                    [
                        'is_active' => true,
                        'contact_email' => config("manufacturers.email_recipients.{$manufacturerName}.0")
                    ]
                );
            }
        }

        return null;
    }

    /**
     * Determine manufacturer from template name (fallback method)
     */
    private function determineManufacturerFromTemplateName(string $templateName): ?Manufacturer
    {
        // Manufacturer name patterns from template names
        $manufacturerPatterns = [
            'ACZ' => ['acz', 'advanced clinical zone'],
            'Integra' => ['integra'],
            'Kerecis' => ['kerecis'],
            'MiMedx' => ['mimedx', 'mimedx group', 'amnio amp'],
            'Organogenesis' => ['organogenesis', 'apligraf', 'dermagraft'],
            'Smith & Nephew' => ['smith', 'nephew', 'smith&nephew'],
            'StimLabs' => ['stimlabs', 'stim labs'],
            'Tissue Tech' => ['tissuetech', 'tissue tech', 'amniograft'],
            'BioWound' => ['biowound', 'bio wound'],
            'Advanced Health' => ['advanced health', 'advancedhealth'],
            'AmnioBand' => ['amnioband', 'amnio band'],
            'BioWerX' => ['biowerx', 'bio werx'],
            'SKYE' => ['skye'],
            'Extremity Care' => ['extremity care', 'extremitycare'],
            'Total Ancillary' => ['total ancillary', 'ancillary']
        ];

        $templateNameLower = strtolower($templateName);

        foreach ($manufacturerPatterns as $manufacturerName => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($templateNameLower, strtolower($pattern)) !== false) {
                    return Manufacturer::firstOrCreate(
                        ['name' => $manufacturerName],
                        [
                            'is_active' => true,
                            'contact_email' => config("manufacturers.email_recipients.{$manufacturerName}.0")
                        ]
                    );
                }
            }
        }

        // If no specific manufacturer found, log warning but don't return null
        $this->warn("⚠️  Could not determine manufacturer for template: {$templateName}");
        return null;
    }

    /**
     * Determine document type from template name or metadata
     */
    private function determineDocumentType(string $templateName, array $templateData): string
    {
        $templateNameLower = strtolower($templateName);

        // Document type patterns
        if (strpos($templateNameLower, 'ivr') !== false || 
            strpos($templateNameLower, 'prior auth') !== false ||
            strpos($templateNameLower, 'authorization') !== false) {
            return 'IVR';
        }

        if (strpos($templateNameLower, 'onboarding') !== false ||
            strpos($templateNameLower, 'enrollment') !== false) {
            return 'OnboardingForm';
        }

        if (strpos($templateNameLower, 'order') !== false ||
            strpos($templateNameLower, 'purchase') !== false) {
            return 'OrderForm';
        }

        if (strpos($templateNameLower, 'insurance') !== false ||
            strpos($templateNameLower, 'verification') !== false) {
            return 'InsuranceVerification';
        }

        // Default to IVR if uncertain
        return 'IVR';
    }

    /**
     * Sync template to database
     */
    private function syncTemplateToDatabase(
        array $templateData,
        array $detailedTemplate,
        ?Manufacturer $manufacturer,
        string $documentType
    ): DocusealTemplate {
        $templateId = $templateData['id'];
        $templateName = $templateData['name'];

        // Extract field mappings from template structure
        $fieldMappings = $this->extractFieldMappings($detailedTemplate);

        // Create or update template record
        $template = DocusealTemplate::updateOrCreate(
            ['docuseal_template_id' => $templateId],
            [
                'template_name' => $templateName,
                'manufacturer_id' => $manufacturer?->id,
                'document_type' => $documentType,
                'is_default' => $this->isDefaultTemplate($templateName, $manufacturer, $documentType),
                'field_mappings' => $fieldMappings,
                'is_active' => true,
                'extraction_metadata' => [
                    'docuseal_created_at' => $templateData['created_at'] ?? null,
                    'docuseal_updated_at' => $templateData['updated_at'] ?? null,
                    'total_fields' => count($fieldMappings),
                    'sync_date' => now()->toISOString(),
                    'sync_version' => '1.0'
                ],
                'field_discovery_status' => 'completed',
                'last_extracted_at' => now()
            ]
        );

        Log::info('DocuSeal template synced', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'manufacturer' => $manufacturer?->name,
            'document_type' => $documentType,
            'field_count' => count($fieldMappings)
        ]);

        return $template;
    }

    /**
     * Extract field mappings from DocuSeal template structure
     */
    private function extractFieldMappings(array $detailedTemplate): array
    {
        $fieldMappings = [];

        // Extract fields from template schema
        $fields = $detailedTemplate['fields'] ?? $detailedTemplate['schema'] ?? [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? $field['id'] ?? null;
            if (!$fieldName) continue;

            $fieldMappings[$fieldName] = [
                'docuseal_field_name' => $fieldName,
                'field_type' => $field['type'] ?? 'text',
                'required' => $field['required'] ?? false,
                'local_field' => $this->mapToLocalField($fieldName),
                'system_field' => $this->mapToSystemField($fieldName),
                'data_type' => $this->determineDataType($field),
                'validation_rules' => $this->extractValidationRules($field),
                'default_value' => $field['default'] ?? null,
                'extracted_at' => now()->toISOString()
            ];
        }

        return $fieldMappings;
    }

    /**
     * Map DocuSeal field name to local system field
     */
    private function mapToLocalField(string $docusealFieldName): string
    {
        $fieldMappings = [
            // Patient fields
            'PATIENT NAME' => 'patientInfo.patientName',
            'PATIENT DOB' => 'patientInfo.dateOfBirth',
            'PATIENT ID' => 'patientInfo.patientId',
            'MEMBER ID' => 'patientInfo.memberId',
            
            // Insurance fields
            'PRIMARY INSURANCE' => 'insuranceInfo.primaryInsurance.name',
            'INSURANCE NAME' => 'insuranceInfo.primaryInsurance.name',
            'GROUP NUMBER' => 'insuranceInfo.primaryInsurance.groupNumber',
            'PAYER PHONE' => 'insuranceInfo.primaryInsurance.payerPhone',
            
            // Provider fields
            'PHYSICIAN NAME' => 'providerInfo.providerName',
            'PROVIDER NAME' => 'providerInfo.providerName',
            'NPI' => 'providerInfo.providerNPI',
            'TAX ID' => 'providerInfo.taxId',
            
            // Facility fields
            'FACILITY NAME' => 'facilityInfo.facilityName',
            'FACILITY ADDRESS' => 'facilityInfo.facilityAddress',
            
            // Sales rep fields
            'REPRESENTATIVE NAME' => 'requestInfo.salesRepName',
            'SALES REP' => 'requestInfo.salesRepName',
        ];

        $upperFieldName = strtoupper($docusealFieldName);
        return $fieldMappings[$upperFieldName] ?? $docusealFieldName;
    }

    /**
     * Map to system field path for QuickRequest integration
     */
    private function mapToSystemField(string $docusealFieldName): string
    {
        // This maps to the actual data structure from QuickRequest
        $systemMappings = [
            'PATIENT NAME' => 'patient_name',
            'PATIENT DOB' => 'patient_dob',
            'MEMBER ID' => 'patient_member_id',
            'PRIMARY INSURANCE' => 'payer_name',
            'GROUP NUMBER' => 'group_number',
            'PHYSICIAN NAME' => 'provider_name',
            'NPI' => 'provider_npi',
            'FACILITY NAME' => 'facility_name',
            'REPRESENTATIVE NAME' => 'sales_rep_name',
        ];

        $upperFieldName = strtoupper($docusealFieldName);
        return $systemMappings[$upperFieldName] ?? Str::snake($docusealFieldName);
    }

    /**
     * Determine data type from field structure
     */
    private function determineDataType(array $field): string
    {
        $fieldType = $field['type'] ?? 'text';
        
        $typeMapping = [
            'date' => 'date',
            'number' => 'number',
            'email' => 'email',
            'phone' => 'phone',
            'checkbox' => 'boolean',
            'select' => 'select',
            'text' => 'string',
            'textarea' => 'text'
        ];

        return $typeMapping[$fieldType] ?? 'string';
    }

    /**
     * Extract validation rules from field
     */
    private function extractValidationRules(array $field): array
    {
        $rules = [];

        if ($field['required'] ?? false) {
            $rules[] = 'required';
        }

        if (isset($field['maxlength'])) {
            $rules[] = 'max:' . $field['maxlength'];
        }

        if (isset($field['minlength'])) {
            $rules[] = 'min:' . $field['minlength'];
        }

        $fieldType = $field['type'] ?? 'text';
        if ($fieldType === 'email') {
            $rules[] = 'email';
        } elseif ($fieldType === 'date') {
            $rules[] = 'date';
        } elseif ($fieldType === 'number') {
            $rules[] = 'numeric';
        }

        return $rules;
    }

    /**
     * Determine if this should be the default template for this manufacturer/type
     */
    private function isDefaultTemplate(string $templateName, ?Manufacturer $manufacturer, string $documentType): bool
    {
        if (!$manufacturer) {
            return false;
        }

        // Check if there's already a default template for this manufacturer/type
        $existingDefault = DocusealTemplate::where('manufacturer_id', $manufacturer->id)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->exists();

        // If no default exists, make this one the default
        return !$existingDefault;
    }
}

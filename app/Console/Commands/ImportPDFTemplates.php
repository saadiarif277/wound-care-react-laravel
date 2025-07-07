<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PDF\ManufacturerPdfTemplate;
use App\Models\PDF\PdfFieldMapping;
use App\Services\PDF\AzurePDFStorageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportPDFTemplates extends Command
{
    protected $signature = 'pdf:import-templates 
                            {--manufacturer= : Import templates for specific manufacturer}
                            {--dry-run : Show what would be imported without making changes}';

    protected $description = 'Import PDF templates from docs/ivr-forms directory to database and Azure';

    private AzurePDFStorageService $azureService;

    public function __construct(AzurePDFStorageService $azureService)
    {
        parent::__construct();
        $this->azureService = $azureService;
    }

    public function handle()
    {
        $this->info('Starting PDF template import...');
        
        $basePath = base_path('docs/ivr-forms');
        $dryRun = $this->option('dry-run');
        $specificManufacturer = $this->option('manufacturer');
        
        if (!File::exists($basePath)) {
            $this->error("Directory not found: $basePath");
            return 1;
        }

        // Map directory names to manufacturer config files
        $manufacturerMapping = [
            'ACZ' => 'acz-associates',
            'Advanced Health (Complete AA)' => 'advanced-health',
            'BioWerX' => 'biowerx',
            'BioWound' => 'biowound-solutions',
            'BioWound Onboarding' => 'biowound-solutions',
            'Centurion Therapeutics' => 'centurion-therapeutics',
            'Extremity Care' => 'extremity-care-llc',
            'Extremity Care Onboarding' => 'extremity-care-llc',
            'Medlife' => 'medlife-solutions',
            'SKYE Onboarding' => 'skye-biologics',
            'Total Ancillary Forms' => 'total-ancillary',
            'Amnio Amp-MSC BAA' => 'medlife-solutions',
            'AmnioBand' => 'centurion-therapeutics',
        ];

        $imported = 0;
        $skipped = 0;

        foreach (File::directories($basePath) as $manufacturerDir) {
            $dirName = basename($manufacturerDir);
            
            // Skip MSC Forms directory - these are internal forms
            if ($dirName === 'MSC Forms') {
                continue;
            }
            
            $configName = $manufacturerMapping[$dirName] ?? null;
            
            if (!$configName) {
                $this->warn("No mapping found for directory: $dirName");
                continue;
            }
            
            if ($specificManufacturer && $configName !== $specificManufacturer) {
                continue;
            }
            
            // Load manufacturer config
            $configPath = config_path("manufacturers/{$configName}.php");
            if (!File::exists($configPath)) {
                $this->warn("Config file not found: $configPath");
                continue;
            }
            
            $config = require $configPath;
            $this->info("\nProcessing manufacturer: {$config['name']} ($dirName)");
            
            // Find PDF files
            $pdfFiles = File::glob($manufacturerDir . '/*.pdf');
            
            foreach ($pdfFiles as $pdfFile) {
                $fileName = basename($pdfFile);
                $this->line("  - Found: $fileName");
                
                if ($dryRun) {
                    $this->info("    [DRY RUN] Would import this template");
                    continue;
                }
                
                try {
                    // Check if template already exists
                    $existing = ManufacturerPdfTemplate::where('manufacturer_id', $config['id'])
                        ->where('template_name', $fileName)
                        ->first();
                    
                    if ($existing) {
                        $this->line("    Template already exists, updating...");
                    }
                    
                    // Extract form fields using PDFtk
                    $fields = $this->extractFormFields($pdfFile);
                    $this->info("    Found " . count($fields) . " form fields");
                    
                    // Upload to Azure
                    $blobPath = $this->azureService->uploadTemplate(
                        $pdfFile,
                        Str::slug($config['name']),
                        $this->getDocumentType($fileName),
                        '1.0'
                    );
                    
                    // Create or update template record
                    $template = ManufacturerPdfTemplate::updateOrCreate(
                        [
                            'manufacturer_id' => $config['id'],
                            'template_name' => $fileName,
                        ],
                        [
                            'document_type' => $this->getDocumentType($fileName),
                            'version' => '1.0',
                            'file_path' => $blobPath,
                            'azure_container' => config('pdf.azure.template_container'),
                            'is_active' => true,
                            'template_fields' => $fields,
                            'metadata' => [
                                'original_file' => $fileName,
                                'imported_from' => $dirName,
                                'imported_at' => now()->toISOString(),
                                'field_count' => count($fields),
                            ]
                        ]
                    );
                    
                    // Create field mappings from config
                    $this->createFieldMappings($template, $config, $fields);
                    
                    $this->info("    ✓ Successfully imported");
                    $imported++;
                    
                } catch (\Exception $e) {
                    $this->error("    ✗ Failed to import: " . $e->getMessage());
                    $skipped++;
                }
            }
        }
        
        $this->info("\nImport complete!");
        $this->info("Imported: $imported templates");
        $this->info("Skipped: $skipped templates");
        
        return 0;
    }
    
    /**
     * Extract form fields from PDF using PDFtk
     */
    private function extractFormFields(string $pdfPath): array
    {
        $fields = [];
        
        // Use pdftk to dump form field data
        $output = shell_exec("pdftk \"$pdfPath\" dump_data_fields 2>&1");
        
        if (!$output) {
            return $fields;
        }
        
        // Parse the output
        $currentField = [];
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'FieldName:') === 0) {
                if (!empty($currentField)) {
                    $fields[] = $currentField;
                }
                $currentField = [
                    'name' => trim(substr($line, 10)),
                    'type' => 'Text',
                    'options' => []
                ];
            } elseif (strpos($line, 'FieldType:') === 0 && !empty($currentField)) {
                $currentField['type'] = trim(substr($line, 10));
            } elseif (strpos($line, 'FieldStateOption:') === 0 && !empty($currentField)) {
                $currentField['options'][] = trim(substr($line, 17));
            }
        }
        
        if (!empty($currentField)) {
            $fields[] = $currentField;
        }
        
        return $fields;
    }
    
    /**
     * Determine document type from filename
     */
    private function getDocumentType(string $fileName): string
    {
        $fileName = strtolower($fileName);
        
        if (strpos($fileName, 'order') !== false) {
            return 'order_form';
        }
        
        return 'ivr';
    }
    
    /**
     * Create field mappings from manufacturer config
     */
    private function createFieldMappings(ManufacturerPdfTemplate $template, array $config, array $pdfFields): void
    {
        // Get PDF field names as a flat array for matching
        $pdfFieldNames = array_map(function($field) {
            return $field['name'];
        }, $pdfFields);
        
        // Create mappings based on config
        foreach ($config['fields'] as $dataField => $fieldConfig) {
            // Try to find matching PDF field
            $pdfFieldName = $this->findMatchingPdfField($dataField, $fieldConfig, $pdfFieldNames, $config);
            
            if (!$pdfFieldName) {
                $this->warn("    No PDF field match found for: $dataField");
                continue;
            }
            
            // Find the field type from PDF fields
            $fieldType = 'text';
            foreach ($pdfFields as $pdfField) {
                if ($pdfField['name'] === $pdfFieldName) {
                    $fieldType = $this->mapPdfFieldType($pdfField['type']);
                    break;
                }
            }
            
            PdfFieldMapping::updateOrCreate(
                [
                    'template_id' => $template->id,
                    'pdf_field_name' => $pdfFieldName,
                ],
                [
                    'data_source' => $fieldConfig['source'],
                    'field_type' => $fieldType,
                    'transform_function' => $fieldConfig['transform'] ?? null,
                    'is_required' => $fieldConfig['required'] ?? false,
                    'default_value' => $fieldConfig['default'] ?? null,
                    'validation_rules' => $this->buildValidationRules($fieldConfig),
                ]
            );
        }
    }
    
    /**
     * Find matching PDF field name
     */
    private function findMatchingPdfField(string $dataField, array $fieldConfig, array $pdfFieldNames, array $config): ?string
    {
        // First check if there's a direct mapping in docuseal_field_names
        if (isset($config['docuseal_field_names'][$dataField])) {
            $mappedName = $config['docuseal_field_names'][$dataField];
            
            // Try exact match
            if (in_array($mappedName, $pdfFieldNames)) {
                return $mappedName;
            }
            
            // Try case-insensitive match
            foreach ($pdfFieldNames as $pdfField) {
                if (strcasecmp($pdfField, $mappedName) === 0) {
                    return $pdfField;
                }
            }
        }
        
        // Try various naming conventions
        $variations = [
            $dataField,
            str_replace('_', ' ', $dataField),
            str_replace('_', '', $dataField),
            strtoupper($dataField),
            strtoupper(str_replace('_', ' ', $dataField)),
            ucwords(str_replace('_', ' ', $dataField)),
        ];
        
        foreach ($variations as $variation) {
            if (in_array($variation, $pdfFieldNames)) {
                return $variation;
            }
        }
        
        // Try partial matches
        foreach ($pdfFieldNames as $pdfField) {
            if (stripos($pdfField, $dataField) !== false) {
                return $pdfField;
            }
        }
        
        return null;
    }
    
    /**
     * Map PDFtk field type to our field type
     */
    private function mapPdfFieldType(string $pdftkType): string
    {
        $mapping = [
            'Text' => 'text',
            'Button' => 'checkbox',
            'Choice' => 'select',
            'Signature' => 'signature',
        ];
        
        return $mapping[$pdftkType] ?? 'text';
    }
    
    /**
     * Build validation rules from field config
     */
    private function buildValidationRules(array $fieldConfig): array
    {
        $rules = [];
        
        if ($fieldConfig['required'] ?? false) {
            $rules['required'] = true;
        }
        
        switch ($fieldConfig['type'] ?? 'string') {
            case 'email':
                $rules['email'] = true;
                break;
            case 'phone':
                $rules['phone'] = true;
                break;
            case 'zip':
                $rules['regex'] = '/^\d{5}(-\d{4})?$/';
                break;
            case 'npi':
                $rules['regex'] = '/^\d{10}$/';
                break;
            case 'date':
                $rules['date'] = true;
                break;
            case 'number':
                $rules['numeric'] = true;
                break;
        }
        
        return $rules;
    }
}
<?php

namespace App\Console\Commands;

use App\Models\Docuseal\DocusealTemplate;
use App\Models\PdfFieldMetadata;
use App\Services\AI\PdfFieldExtractorService;
use App\Services\AI\SmartFieldMappingValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ExtractPdfFieldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pdf:extract-fields
                            {--template= : Extract fields from specific template ID}
                            {--manufacturer= : Extract fields from specific manufacturer}
                            {--dry-run : Show what would be extracted without actually doing it}
                            {--force : Force re-extraction even if fields already exist}
                            {--verify : Verify extraction results against existing field mappings}
                            {--stats : Show field extraction statistics}
                            {--methods= : Comma-separated list of extraction methods (pypdf2,pdfplumber,pymupdf)}';

    /**
     * The console command description.
     */
    protected $description = 'Extract field metadata from PDF templates using PyPDF2, pdfplumber, and pymupdf';

    private PdfFieldExtractorService $extractorService;
    private SmartFieldMappingValidator $validator;

    public function __construct(
        PdfFieldExtractorService $extractorService,
        SmartFieldMappingValidator $validator
    ) {
        parent::__construct();
        $this->extractorService = $extractorService;
        $this->validator = $validator;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 PDF Field Extractor Starting...');
        
        // Check if this is a stats request
        if ($this->option('stats')) {
            $this->showStatistics();
            return 0;
        }
        
        // Check Python dependencies
        if (!$this->checkPythonDependencies()) {
            $this->error('❌ Python dependencies not available. Please install them first.');
            $this->line('Run: cd scripts && pip install -r requirements.txt');
            return 1;
        }
        
        // Get templates to process
        $templates = $this->getTemplatesToProcess();
        
        if ($templates->isEmpty()) {
            $this->warn('No templates found to process.');
            return 0;
        }
        
        $this->info("📋 Found {$templates->count()} templates to process");
        
        if ($this->option('dry-run')) {
            $this->showDryRun($templates);
            return 0;
        }
        
        // Process templates
        $results = $this->processTemplates($templates);
        
        // Show results
        $this->showResults($results);
        
        // Verify against existing mappings if requested
        if ($this->option('verify')) {
            $this->verifyResults($results);
        }
        
        // Update SmartFieldMappingValidator with new data
        $this->extractorService->updateValidatorWithExtractedFields();
        $this->info('✅ Updated SmartFieldMappingValidator with extracted field data');
        
        return 0;
    }
    
    private function checkPythonDependencies(): bool
    {
        $this->info('🔧 Checking Python dependencies...');
        
        $scriptsPath = base_path('scripts');
        $command = "cd {$scriptsPath} && /bin/bash -c 'source ai_service_env/bin/activate && python3 -c \"import PyPDF2, pdfplumber, fitz; print(\\\"OK\\\")\"' 2>/dev/null";
        
        $output = shell_exec($command);
        $available = trim($output) === 'OK';
        
        if ($available) {
            $this->info('✅ Python dependencies are available');
        } else {
            $this->error('❌ Python dependencies missing');
            $this->line('Available libraries:');
            
            // Check individual libraries
            $libraries = ['PyPDF2', 'pdfplumber', 'fitz'];
            foreach ($libraries as $lib) {
                $checkCmd = "cd {$scriptsPath} && /bin/bash -c 'source ai_service_env/bin/activate && python3 -c \"import {$lib}; print(\\\"✅ {$lib}\\\")\"' 2>/dev/null || echo '❌ {$lib}'";
                $result = shell_exec($checkCmd);
                $this->line("  " . trim($result));
            }
        }
        
        return $available;
    }
    
    private function getTemplatesToProcess(): \Illuminate\Database\Eloquent\Collection
    {
        $query = DocusealTemplate::with('manufacturer');
        
        // Filter by specific template
        if ($templateId = $this->option('template')) {
            $query->where('docuseal_template_id', $templateId);
        }
        
        // Filter by manufacturer
        if ($manufacturer = $this->option('manufacturer')) {
            $query->whereHas('manufacturer', function($q) use ($manufacturer) {
                $q->where('name', 'like', "%{$manufacturer}%");
            });
        }
        
        // Skip templates that already have extracted fields (unless --force)
        if (!$this->option('force')) {
            $query->whereDoesntHave('pdfFieldMetadata');
        }
        
        return $query->get();
    }
    
    private function showDryRun(\Illuminate\Database\Eloquent\Collection $templates): void
    {
        $this->warn('🔍 DRY RUN MODE - No changes will be made');
        $this->line('');
        
        $this->table(
            ['Template ID', 'Template Name', 'Manufacturer', 'Existing Fields'],
            $templates->map(function($template) {
                return [
                    $template->docuseal_template_id,
                    $template->template_name,
                    $template->manufacturer?->name ?? 'Unknown',
                    PdfFieldMetadata::where('docuseal_template_id', $template->docuseal_template_id)->count()
                ];
            })->toArray()
        );
        
        $this->line('');
        $this->info('To run the extraction, remove the --dry-run flag');
    }
    
    private function processTemplates(\Illuminate\Database\Eloquent\Collection $templates): array
    {
        $results = [];
        $progressBar = $this->output->createProgressBar($templates->count());
        
        $this->line('');
        $this->info('📊 Starting extraction...');
        $progressBar->start();
        
        foreach ($templates as $template) {
            try {
                $this->line('');
                $this->info("🔍 Processing: {$template->template_name}");
                
                // Extract fields
                $result = $this->extractorService->extractTemplateFields($template);
                
                $results[] = $result;
                
                // Show progress
                $fieldCount = $result['field_count'] ?? 0;
                $this->line("  ✅ Extracted {$fieldCount} fields");
                
                if ($fieldCount > 0) {
                    $fields = $result['stored_fields'] ?? [];
                    foreach (array_slice($fields, 0, 3) as $field) {
                        $this->line("    • {$field}");
                    }
                    if (count($fields) > 3) {
                        $this->line("    ... and " . (count($fields) - 3) . " more");
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Failed to process {$template->template_name}: {$e->getMessage()}");
                Log::error("PDF field extraction failed", [
                    'template' => $template->template_name,
                    'error' => $e->getMessage()
                ]);
                
                $results[] = [
                    'template_id' => $template->docuseal_template_id,
                    'template_name' => $template->template_name,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->line('');
        
        return $results;
    }
    
    private function showResults(array $results): void
    {
        $this->line('');
        $this->info('📊 Extraction Results:');
        
        $successful = collect($results)->where('success', true);
        $failed = collect($results)->where('success', false);
        
        $this->table(
            ['Status', 'Template Name', 'Fields Extracted', 'Details'],
            collect($results)->map(function($result) {
                return [
                    $result['success'] ? '✅ Success' : '❌ Failed',
                    $result['template_name'],
                    $result['field_count'] ?? 0,
                    $result['success'] 
                        ? 'Extracted successfully' 
                        : ($result['error'] ?? 'Unknown error')
                ];
            })->toArray()
        );
        
        $this->line('');
        $this->info("✅ Successfully processed: {$successful->count()}");
        if ($failed->count() > 0) {
            $this->warn("❌ Failed to process: {$failed->count()}");
        }
        
        $totalFields = $successful->sum('field_count');
        $this->info("📋 Total fields extracted: {$totalFields}");
    }
    
    private function verifyResults(array $results): void
    {
        $this->line('');
        $this->info('🔍 Verifying extraction results against existing field mappings...');
        
        $successfulResults = collect($results)->where('success', true);
        $verificationResults = [];
        
        foreach ($successfulResults as $result) {
            $templateId = $result['template_id'];
            $fields = $result['stored_fields'] ?? [];
            
            // Get manufacturer configurations that might use this template
            $manufacturer = DocusealTemplate::where('docuseal_template_id', $templateId)->first()?->manufacturer;
            
            if ($manufacturer) {
                // Create a temporary field mapping to validate
                $tempMappings = array_combine($fields, $fields);
                $validationResult = $this->validator->validateAndCorrectFieldMappings($tempMappings, $manufacturer->name);
                
                // Check for removed invalid fields
                foreach ($validationResult['removed_invalid'] as $fieldName) {
                    $verificationResults[] = [
                        'template' => $result['template_name'],
                        'field' => $fieldName,
                        'manufacturer' => $manufacturer->name,
                        'issue' => 'Invalid field name',
                        'suggestion' => 'None'
                    ];
                }
                
                // Check for corrections
                foreach ($validationResult['corrections'] as $correction) {
                    $verificationResults[] = [
                        'template' => $result['template_name'],
                        'field' => $correction['original'],
                        'manufacturer' => $manufacturer->name,
                        'issue' => 'Field name corrected',
                        'suggestion' => $correction['corrected'] . " (confidence: {$correction['confidence']})"
                    ];
                }
            }
        }
        
        if (empty($verificationResults)) {
            $this->info('✅ All extracted fields appear to be valid');
        } else {
            $this->warn("⚠️  Found {count($verificationResults)} potential field mapping issues:");
            $this->table(
                ['Template', 'Field', 'Manufacturer', 'Issue', 'Suggestion'],
                $verificationResults
            );
        }
    }
    
    private function showStatistics(): void
    {
        $this->info('📊 PDF Field Extraction Statistics:');
        $this->line('');
        
        $stats = $this->extractorService->getFieldStatistics();
        
        // General statistics
        $this->info("📋 Total Fields: {$stats['total_fields']}");
        $this->info("✅ Verified Fields: {$stats['verified_fields']}");
        $this->info("🎯 High Confidence Fields: {$stats['high_confidence_fields']}");
        $this->line('');
        
        // Fields by type
        if (!empty($stats['fields_by_type'])) {
            $this->info('📑 Fields by Type:');
            $this->table(
                ['Type', 'Count'],
                $stats['fields_by_type']->map(function($item) {
                    return [$item->field_type, $item->count];
                })->toArray()
            );
        }
        
        // Fields by medical category
        if (!empty($stats['fields_by_category'])) {
            $this->info('🏥 Fields by Medical Category:');
            $this->table(
                ['Category', 'Count'],
                $stats['fields_by_category']->map(function($item) {
                    return [$item->medical_category ?? 'Unknown', $item->count];
                })->toArray()
            );
        }
        
        // Fields by manufacturer
        if (!empty($stats['fields_by_manufacturer'])) {
            $this->info('🏭 Fields by Manufacturer:');
            $manufacturerStats = collect($stats['fields_by_manufacturer'])
                ->map(function($count, $name) {
                    return [$name ?? 'Unknown', $count];
                })
                ->values()
                ->toArray();
            
            $this->table(['Manufacturer', 'Count'], $manufacturerStats);
        }
        
        // Extraction methods
        if (!empty($stats['extraction_methods'])) {
            $this->info('🔧 Extraction Methods Used:');
            $this->table(
                ['Method', 'Count'],
                $stats['extraction_methods']->map(function($item) {
                    return [$item->extraction_method, $item->count];
                })->toArray()
            );
        }
        
        // Recent activity
        $recentExtractions = PdfFieldMetadata::where('created_at', '>=', now()->subDays(7))->count();
        $this->line('');
        $this->info("📅 Fields extracted in last 7 days: {$recentExtractions}");
        
        // Recommendations
        $this->line('');
        $this->info('💡 Recommendations:');
        
        $unverifiedCount = $stats['total_fields'] - $stats['verified_fields'];
        if ($unverifiedCount > 0) {
            $this->line("  • Review and verify {$unverifiedCount} unverified fields");
        }
        
        $lowConfidenceCount = $stats['total_fields'] - $stats['high_confidence_fields'];
        if ($lowConfidenceCount > 0) {
            $this->line("  • Review {$lowConfidenceCount} low-confidence field extractions");
        }
        
        if ($stats['total_fields'] === 0) {
            $this->line("  • Run field extraction: php artisan pdf:extract-fields");
        }
    }
}

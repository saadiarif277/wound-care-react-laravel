<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Docuseal\DocusealTemplate;
use App\Services\OcrFieldDetectionService;
use App\Models\Order\Manufacturer;

class EnhanceDocuSealFieldsWithOcrCommand extends Command
{
    protected $signature = 'docuseal:enhance-with-ocr 
                           {--template-id= : Specific DocuSeal template ID to enhance}
                           {--manufacturer= : Enhance templates for specific manufacturer}
                           {--all : Enhance all templates}
                           {--force : Force re-process even if OCR data exists}
                           {--save-pdfs : Save downloaded PDFs for inspection}';

    protected $description = 'Enhance DocuSeal field mappings using OCR-based field detection';

    private OcrFieldDetectionService $ocrFieldDetection;

    public function __construct(OcrFieldDetectionService $ocrFieldDetection)
    {
        parent::__construct();
        $this->ocrFieldDetection = $ocrFieldDetection;
    }

    public function handle(): int
    {
        $this->info('🔍 Enhanced DocuSeal Field Detection with OCR');
        $this->newLine();

        try {
            if ($templateId = $this->option('template-id')) {
                return $this->enhanceSpecificTemplate($templateId);
            }

            if ($manufacturer = $this->option('manufacturer')) {
                return $this->enhanceManufacturerTemplates($manufacturer);
            }

            if ($this->option('all')) {
                return $this->enhanceAllTemplates();
            }

            $this->error('Please specify --template-id, --manufacturer, or --all');
            return 1;

        } catch (\Exception $e) {
            $this->error("Enhancement failed: {$e->getMessage()}");
            Log::error('DocuSeal OCR enhancement failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function enhanceSpecificTemplate(string $templateId): int
    {
        $this->info("🎯 Enhancing specific template: {$templateId}");
        
        $template = DocusealTemplate::where('docuseal_template_id', $templateId)->first();
        if (!$template) {
            $this->error("Template not found in database: {$templateId}");
            return 1;
        }

        $result = $this->processTemplateWithOcr($template);
        $this->displayEnhancementResult($result);

        return 0;
    }

    private function enhanceManufacturerTemplates(string $manufacturerName): int
    {
        $this->info("🏭 Enhancing templates for manufacturer: {$manufacturerName}");
        
        $manufacturer = Manufacturer::where('name', 'like', "%{$manufacturerName}%")->first();
        if (!$manufacturer) {
            $this->error("Manufacturer not found: {$manufacturerName}");
            return 1;
        }

        $templates = DocusealTemplate::where('manufacturer_id', $manufacturer->id)->get();
        $this->info("Found {$templates->count()} templates for {$manufacturer->name}");

        $results = [];
        foreach ($templates as $template) {
            $this->newLine();
            $this->info("Processing: {$template->template_name}");
            $result = $this->processTemplateWithOcr($template);
            $results[] = $result;
            $this->displayEnhancementResult($result);
        }

        $this->displaySummaryResults($results);
        return 0;
    }

    private function enhanceAllTemplates(): int
    {
        $this->info("📋 Enhancing all templates");
        
        $templates = DocusealTemplate::with('manufacturer')->get();
        $this->info("Found {$templates->count()} templates in database");

        if (!$this->confirm('This will process all templates with OCR. Continue?')) {
            return 0;
        }

        $results = [];
        $progressBar = $this->output->createProgressBar($templates->count());

        foreach ($templates as $template) {
            $result = $this->processTemplateWithOcr($template);
            $results[] = $result;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displaySummaryResults($results);
        return 0;
    }

    private function processTemplateWithOcr(DocusealTemplate $template): array
    {
        $result = [
            'template_id' => $template->docuseal_template_id,
            'template_name' => $template->template_name,
            'manufacturer' => $template->manufacturer->name ?? 'Unknown',
            'success' => false,
            'ocr_fields_found' => 0,
            'docuseal_fields_count' => count($template->field_mappings ?? []),
            'field_improvements' => 0,
            'mapping_suggestions' => 0,
            'error' => null
        ];

        try {
            // Skip if already processed and not forcing
            if (!$this->option('force') && $this->hasOcrData($template)) {
                $result['success'] = true;
                $result['skipped'] = true;
                return $result;
            }

            // Download PDF from DocuSeal
            $pdfPath = $this->downloadTemplatePdf($template);
            if (!$pdfPath) {
                $result['error'] = 'Could not download PDF template';
                return $result;
            }

            // Extract fields using OCR
            $ocrFields = $this->ocrFieldDetection->extractFieldLabelsFromPdf($pdfPath);
            $result['ocr_fields_found'] = count($ocrFields);

            if (empty($ocrFields)) {
                $result['error'] = 'No fields detected via OCR';
                $this->cleanupTempFile($pdfPath);
                return $result;
            }

            // Compare with existing DocuSeal fields
            $docusealFields = $this->extractDocuSealFields($template);
            $comparison = $this->ocrFieldDetection->compareWithDocuSealFields($ocrFields, $docusealFields);

            // Enhance field mappings
            $enhancedMappings = $this->enhanceFieldMappings($template, $ocrFields, $comparison);
            
            // Update template with enhanced mappings
            $this->updateTemplateWithEnhancements($template, $enhancedMappings, $ocrFields, $comparison);

            $result['success'] = true;
            $result['field_improvements'] = count($enhancedMappings['improvements']['improved_fields']);
            $result['mapping_suggestions'] = count($comparison['mapping_suggestions']);

            // Cleanup
            if (!$this->option('save-pdfs')) {
                $this->cleanupTempFile($pdfPath);
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            Log::error('OCR enhancement failed for template', [
                'template_id' => $template->docuseal_template_id,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    private function downloadTemplatePdf(DocusealTemplate $template): ?string
    {
        // First try to find local PDF file
        $localPdfPath = $this->findLocalPdfFile($template);
        if ($localPdfPath) {
            $this->line("    📁 Using local PDF: {$localPdfPath}");
            return $localPdfPath;
        }

        // Fallback to downloading from DocuSeal API
        try {
            $this->line("    🌐 Attempting to download from DocuSeal API...");
            
            // First, get the template details from DocuSeal API to find PDF URL
            $response = Http::withHeaders([
                'X-Auth-Token' => config('docuseal.api_key'),
            ])->get(config('docuseal.api_url') . "/templates/{$template->docuseal_template_id}");

            if (!$response->successful()) {
                $this->warn("Could not fetch template details for {$template->docuseal_template_id}");
                return null;
            }

            $templateData = $response->json();
            $pdfUrl = $templateData['pdf_url'] ?? $templateData['template_url'] ?? null;

            if (!$pdfUrl) {
                $this->warn("No PDF URL found for template {$template->docuseal_template_id}");
                return null;
            }

            // Download the PDF
            $pdfResponse = Http::get($pdfUrl);
            if (!$pdfResponse->successful()) {
                $this->warn("Failed to download PDF from {$pdfUrl}");
                return null;
            }

            // Save to temporary location
            $tempDir = storage_path('app/temp/ocr-pdfs');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempPath = $tempDir . '/' . $template->docuseal_template_id . '.pdf';
            file_put_contents($tempPath, $pdfResponse->body());

            return $tempPath;

        } catch (\Exception $e) {
            $this->warn("Error downloading PDF for template {$template->docuseal_template_id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find local PDF file in docs/ivr-forms directory
     */
    private function findLocalPdfFile(DocusealTemplate $template): ?string
    {
        $docsPath = base_path('docs/ivr-forms');
        if (!file_exists($docsPath)) {
            return null;
        }

        $templateName = $template->template_name;
        $manufacturerName = $template->manufacturer->name ?? '';

        // Search recursively in all subdirectories
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($docsPath)
        );

        $candidates = [];
        
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'pdf') {
                continue;
            }

            $fileName = $file->getBasename('.pdf');
            $filePath = $file->getRealPath();
            $folderName = basename(dirname($filePath));
            
            // Score each file based on how well it matches
            $score = 0;
            
            // Exact template name match gets highest score
            if (strcasecmp($fileName, $templateName) === 0) {
                $score += 100;
            }
            
            // Partial template name matches
            if (stripos($fileName, $templateName) !== false) {
                $score += 80;
            }
            
            // Check if manufacturer folder matches
            if (!empty($manufacturerName)) {
                if (stripos($folderName, $manufacturerName) !== false) {
                    $score += 50;
                }
                
                // Manufacturer name in filename
                if (stripos($fileName, $manufacturerName) !== false) {
                    $score += 30;
                }
            }
            
            // Template-specific keywords (only if manufacturer is matched)
            if ($score >= 50) {
                if (stripos($templateName, 'IVR') !== false && stripos($fileName, 'IVR') !== false) {
                    $score += 20;
                }
                if (stripos($templateName, 'Order') !== false && stripos($fileName, 'Order') !== false) {
                    $score += 20;
                }
                if (stripos($templateName, 'Form') !== false && stripos($fileName, 'Form') !== false) {
                    $score += 10;
                }
            }
            
            // Only consider files with meaningful scores
            if ($score >= 50) {
                $candidates[] = [
                    'path' => $filePath,
                    'name' => $fileName,
                    'folder' => $folderName,
                    'score' => $score
                ];
            }
        }
        
        // Sort by score descending and return the best match
        if (!empty($candidates)) {
            usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
            $bestMatch = $candidates[0];
            $this->line("    ✅ Found local PDF match: {$bestMatch['name']} (score: {$bestMatch['score']})");
            return $bestMatch['path'];
        }

        return null;
    }

    private function extractDocuSealFields(DocusealTemplate $template): array
    {
        $fields = [];
        $fieldMappings = $template->field_mappings ?? [];

        foreach ($fieldMappings as $fieldName => $mapping) {
            $fields[] = [
                'name' => $fieldName,
                'type' => $mapping['field_type'] ?? 'text',
                'required' => $mapping['required'] ?? false,
                'system_field' => $mapping['system_field'] ?? null
            ];
        }

        return $fields;
    }

    private function enhanceFieldMappings(DocusealTemplate $template, array $ocrFields, array $comparison): array
    {
        $improvements = [
            'improved_fields' => [],
            'new_field_labels' => [],
            'better_mappings' => []
        ];

        $currentMappings = $template->field_mappings ?? [];

        // Process mapping suggestions
        foreach ($comparison['mapping_suggestions'] as $suggestion) {
            $docusealFieldName = $suggestion['docuseal_field']['name'];
            $ocrLabel = $suggestion['ocr_field']['label'];
            $systemField = $suggestion['suggested_mapping']['system_field'];

            if (isset($currentMappings[$docusealFieldName])) {
                // Handle both old (string) and new (array) mapping formats
                $existingMapping = $currentMappings[$docusealFieldName];
                
                // Convert old string format to new array format
                if (is_string($existingMapping)) {
                    $existingMapping = [
                        'system_field' => $existingMapping,
                        'field_label' => $docusealFieldName,
                        'field_type' => 'text',
                        'required' => false
                    ];
                }
                
                // Ensure it's an array before trying to modify it
                if (is_array($existingMapping)) {
                    // Update existing mapping with better field label
                    $existingMapping['field_label'] = $ocrLabel;
                    $existingMapping['ocr_detected'] = true;
                    $existingMapping['ocr_confidence'] = $suggestion['ocr_field']['confidence'];
                    
                    // Update system field if OCR suggests a better one
                    if ($suggestion['similarity_score'] > 0.8) {
                        $existingMapping['system_field'] = $systemField;
                        $improvements['better_mappings'][] = [
                            'field' => $docusealFieldName,
                            'old_label' => $docusealFieldName,
                            'new_label' => $ocrLabel,
                            'new_system_field' => $systemField
                        ];
                    }
                    
                    // Save the updated mapping back
                    $currentMappings[$docusealFieldName] = $existingMapping;
                    $improvements['improved_fields'][] = $docusealFieldName;
                }
            }
        }

        // Add field labels for OCR-only fields (potential new fields)
        foreach ($comparison['ocr_only_fields'] as $ocrField) {
            $improvements['new_field_labels'][] = [
                'label' => $ocrField['label'],
                'suggested_system_field' => $ocrField['suggested_system_field'],
                'confidence' => $ocrField['confidence'],
                'type' => $ocrField['type']
            ];
        }

        return [
            'enhanced_mappings' => $currentMappings,
            'improvements' => $improvements
        ];
    }

    private function updateTemplateWithEnhancements(
        DocusealTemplate $template, 
        array $enhancedMappings, 
        array $ocrFields, 
        array $comparison
    ): void {
        $template->update([
            'field_mappings' => $enhancedMappings['enhanced_mappings'],
            'extraction_metadata' => array_merge($template->extraction_metadata ?? [], [
                'ocr_enhanced' => true,
                'ocr_enhancement_date' => now()->toISOString(),
                'ocr_fields_detected' => count($ocrFields),
                'ocr_field_improvements' => count($enhancedMappings['improvements']['improved_fields']),
                'ocr_new_fields' => count($enhancedMappings['improvements']['new_field_labels']),
                'ocr_comparison_summary' => [
                    'matched_fields' => count($comparison['matched_fields']),
                    'ocr_only_fields' => count($comparison['ocr_only_fields']),
                    'docuseal_only_fields' => count($comparison['docuseal_only_fields']),
                    'mapping_suggestions' => count($comparison['mapping_suggestions'])
                ]
            ]),
            'last_extracted_at' => now()
        ]);

        Log::info('Template enhanced with OCR data', [
            'template_id' => $template->docuseal_template_id,
            'template_name' => $template->template_name,
            'ocr_fields_detected' => count($ocrFields),
            'field_improvements' => count($enhancedMappings['improvements']['improved_fields'])
        ]);
    }

    private function hasOcrData(DocusealTemplate $template): bool
    {
        $metadata = $template->extraction_metadata ?? [];
        return isset($metadata['ocr_enhanced']) && $metadata['ocr_enhanced'] === true;
    }

    private function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    private function displayEnhancementResult(array $result): void
    {
        if (isset($result['skipped'])) {
            $this->line("  ⏭️  Skipped (already processed)");
            return;
        }

        if (!$result['success']) {
            $this->error("  ❌ Failed: " . ($result['error'] ?? 'Unknown error'));
            return;
        }

        $this->info("  ✅ Enhanced successfully");
        $this->line("    🔍 OCR Fields Detected: {$result['ocr_fields_found']}");
        $this->line("    📊 DocuSeal Fields: {$result['docuseal_fields_count']}");
        $this->line("    ⬆️  Field Improvements: {$result['field_improvements']}");
        $this->line("    💡 Mapping Suggestions: {$result['mapping_suggestions']}");
    }

    private function displaySummaryResults(array $results): void
    {
        $this->info('📊 OCR Enhancement Summary');
        $this->newLine();

        $totalTemplates = count($results);
        $successful = count(array_filter($results, fn($r) => $r['success']));
        $skipped = count(array_filter($results, fn($r) => isset($r['skipped'])));
        $failed = $totalTemplates - $successful - $skipped;
        
        $totalOcrFields = array_sum(array_column($results, 'ocr_fields_found'));
        $totalImprovements = array_sum(array_column($results, 'field_improvements'));
        $totalSuggestions = array_sum(array_column($results, 'mapping_suggestions'));

        $this->table([
            'Metric', 'Count'
        ], [
            ['Total Templates', $totalTemplates],
            ['Successfully Enhanced', $successful],
            ['Skipped (Already Processed)', $skipped],
            ['Failed', $failed],
            ['Total OCR Fields Detected', $totalOcrFields],
            ['Total Field Improvements', $totalImprovements],
            ['Total Mapping Suggestions', $totalSuggestions],
        ]);

        if ($failed > 0) {
            $this->newLine();
            $this->warn("⚠️  Failed Templates:");
            foreach ($results as $result) {
                if (!$result['success'] && !isset($result['skipped'])) {
                    $this->line("  • {$result['template_name']}: {$result['error']}");
                }
            }
        }

        if ($totalImprovements > 0) {
            $this->newLine();
            $this->info("🎉 Enhancement completed! {$totalImprovements} field mappings improved with OCR data.");
            $this->info("💡 Run the verification command to see detailed comparisons:");
            $this->line("   php artisan docuseal:verify-fields --compare-ocr");
        }
    }
}

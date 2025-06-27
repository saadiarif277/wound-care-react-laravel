<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Docuseal\DocusealTemplate;
use App\Services\AzureDocumentIntelligenceService;
use App\Models\Order\Manufacturer;

class VerifyDocuSealFieldsCommand extends Command
{
    protected $signature = 'docuseal:verify-fields 
                           {--template-id= : Specific DocuSeal template ID to verify}
                           {--manufacturer= : Verify templates for specific manufacturer}
                           {--compare-ocr : Use OCR to compare actual PDF field labels}
                           {--save-results : Save comparison results to file}';

    protected $description = 'Verify DocuSeal field metadata vs actual PDF field labels';

    private AzureDocumentIntelligenceService $ocrService;

    public function __construct(AzureDocumentIntelligenceService $ocrService)
    {
        parent::__construct();
        $this->ocrService = $ocrService;
    }

    public function handle(): int
    {
        $this->info('🔍 DocuSeal Field Verification Tool');
        $this->newLine();

        try {
            if ($templateId = $this->option('template-id')) {
                return $this->verifySpecificTemplate($templateId);
            }

            if ($manufacturer = $this->option('manufacturer')) {
                return $this->verifyManufacturerTemplates($manufacturer);
            }

            return $this->verifyAllTemplates();

        } catch (\Exception $e) {
            $this->error("Verification failed: {$e->getMessage()}");
            Log::error('DocuSeal field verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function verifySpecificTemplate(string $templateId): int
    {
        $this->info("🎯 Verifying specific template: {$templateId}");
        
        // Fetch from DocuSeal API
        $docusealData = $this->fetchDocuSealTemplate($templateId);
        if (!$docusealData) {
            $this->error("Could not fetch template from DocuSeal API");
            return 1;
        }

        // Check database
        $dbTemplate = DocusealTemplate::where('docuseal_template_id', $templateId)->first();
        
        $this->displayTemplateComparison($templateId, $docusealData, $dbTemplate);

        if ($this->option('compare-ocr')) {
            $this->performOCRComparison($templateId, $docusealData);
        }

        return 0;
    }

    private function verifyManufacturerTemplates(string $manufacturerName): int
    {
        $this->info("🏭 Verifying templates for manufacturer: {$manufacturerName}");
        
        $manufacturer = Manufacturer::where('name', 'like', "%{$manufacturerName}%")->first();
        if (!$manufacturer) {
            $this->error("Manufacturer not found: {$manufacturerName}");
            return 1;
        }

        $templates = DocusealTemplate::where('manufacturer_id', $manufacturer->id)->get();
        $this->info("Found {$templates->count()} templates for {$manufacturer->name}");

        foreach ($templates as $template) {
            $this->newLine();
            $docusealData = $this->fetchDocuSealTemplate($template->docuseal_template_id);
            $this->displayTemplateComparison($template->docuseal_template_id, $docusealData, $template);
        }

        return 0;
    }

    private function verifyAllTemplates(): int
    {
        $this->info("📋 Verifying all templates");
        
        $templates = DocusealTemplate::with('manufacturer')->get();
        $this->info("Found {$templates->count()} templates in database");

        $results = [];
        $progressBar = $this->output->createProgressBar($templates->count());

        foreach ($templates as $template) {
            $docusealData = $this->fetchDocuSealTemplate($template->docuseal_template_id);
            $comparison = $this->analyzeFieldMismatch($template, $docusealData);
            $results[] = $comparison;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displaySummaryResults($results);

        if ($this->option('save-results')) {
            $this->saveResultsToFile($results);
        }

        return 0;
    }

    private function fetchDocuSealTemplate(string $templateId): ?array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => config('docuseal.api_key'),
            ])->get(config('docuseal.api_url') . "/templates/{$templateId}");

            if (!$response->successful()) {
                $this->warn("Failed to fetch template {$templateId}: " . $response->status());
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            $this->warn("Error fetching template {$templateId}: " . $e->getMessage());
            return null;
        }
    }

    private function displayTemplateComparison(string $templateId, ?array $docusealData, ?DocusealTemplate $dbTemplate): void
    {
        $this->info("📄 Template: {$templateId}");
        
        if ($dbTemplate) {
            $this->line("  🏷️  Name: {$dbTemplate->template_name}");
            $this->line("  🏭 Manufacturer: " . ($dbTemplate->manufacturer->name ?? 'Unknown'));
            $this->line("  📋 Type: {$dbTemplate->document_type}");
        }

        if (!$docusealData) {
            $this->error("  ❌ No DocuSeal API data available");
            return;
        }

        // Extract fields from DocuSeal API
        $apiFields = $docusealData['fields'] ?? $docusealData['schema'] ?? [];
        $this->line("  🔧 API Fields Found: " . count($apiFields));

        if (empty($apiFields)) {
            $this->warn("  ⚠️  No fields found in API response");
            return;
        }

        $this->newLine();
        $this->line("  📊 Field Analysis:");

        foreach ($apiFields as $index => $field) {
            $fieldName = $field['name'] ?? $field['id'] ?? "field_{$index}";
            $fieldType = $field['type'] ?? 'unknown';
            $fieldLabel = $field['label'] ?? $field['title'] ?? null;
            
            $this->line("    • {$fieldName} ({$fieldType})");
            if ($fieldLabel && $fieldLabel !== $fieldName) {
                $this->line("      Label: {$fieldLabel}");
            }
            
            // Check if this looks like auto-generated metadata
            if (preg_match('/^(field_\d+|text_\d+|input_\d+)$/i', $fieldName)) {
                $this->line("      ⚠️  Appears to be auto-generated field name");
            }
        }

        // Compare with database mappings
        if ($dbTemplate && $dbTemplate->field_mappings) {
            $this->newLine();
            $this->line("  🗃️  Database Mappings:");
            $mappingCount = count($dbTemplate->field_mappings);
            $this->line("    Stored Mappings: {$mappingCount}");
            
            $apiFieldNames = array_map(fn($f) => $f['name'] ?? $f['id'] ?? null, $apiFields);
            $dbFieldNames = array_keys($dbTemplate->field_mappings);
            
            $missing = array_diff($apiFieldNames, $dbFieldNames);
            $extra = array_diff($dbFieldNames, $apiFieldNames);
            
            if (!empty($missing)) {
                $this->warn("    Missing from DB: " . implode(', ', $missing));
            }
            if (!empty($extra)) {
                $this->warn("    Extra in DB: " . implode(', ', $extra));
            }
        }
    }

    private function performOCRComparison(string $templateId, array $docusealData): void
    {
        $this->info("🔎 Performing OCR comparison...");
        
        // Try to download PDF template from DocuSeal
        $pdfUrl = $docusealData['pdf_url'] ?? $docusealData['template_url'] ?? null;
        if (!$pdfUrl) {
            $this->warn("No PDF URL available for OCR analysis");
            return;
        }

        try {
            // Download PDF
            $pdfResponse = Http::get($pdfUrl);
            if (!$pdfResponse->successful()) {
                $this->warn("Failed to download PDF from DocuSeal");
                return;
            }

            // Save temporarily
            $tempPath = storage_path('app/temp/' . $templateId . '.pdf');
            file_put_contents($tempPath, $pdfResponse->body());

            // Perform OCR
            $this->line("  🤖 Running Azure Document Intelligence...");
            $ocrResult = $this->ocrService->analyzeDocument($tempPath, 'prebuilt-layout');

            if ($ocrResult && isset($ocrResult['analyzeResult']['tables'])) {
                $this->displayOCRFields($ocrResult);
            } else {
                $this->warn("OCR analysis did not return expected field data");
            }

            // Cleanup
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

        } catch (\Exception $e) {
            $this->warn("OCR analysis failed: " . $e->getMessage());
        }
    }

    private function displayOCRFields(array $ocrResult): void
    {
        $this->line("  📝 OCR Detected Fields:");
        
        // Extract text content that looks like field labels
        $content = $ocrResult['analyzeResult']['content'] ?? '';
        $lines = explode('\n', $content);
        
        $fieldPatterns = [
            '/^([A-Z\s]+):?\s*_+\s*$/',  // PATIENT NAME: ______
            '/^([A-Z\s]+):?\s*\[\s*\]\s*$/',  // PATIENT NAME: [ ]
            '/^([A-Z\s]+):?\s*$/',  // PATIENT NAME:
        ];
        
        $detectedFields = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            foreach ($fieldPatterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $fieldLabel = trim($matches[1]);
                    if (strlen($fieldLabel) > 3) {  // Filter out short noise
                        $detectedFields[] = $fieldLabel;
                    }
                }
            }
        }
        
        $detectedFields = array_unique($detectedFields);
        foreach ($detectedFields as $field) {
            $this->line("    • {$field}");
        }
        
        if (empty($detectedFields)) {
            $this->warn("  No clear field labels detected via OCR");
        }
    }

    private function analyzeFieldMismatch(DocusealTemplate $template, ?array $docusealData): array
    {
        $result = [
            'template_id' => $template->docuseal_template_id,
            'template_name' => $template->template_name,
            'manufacturer' => $template->manufacturer->name ?? 'Unknown',
            'has_api_data' => !is_null($docusealData),
            'api_field_count' => 0,
            'db_field_count' => count($template->field_mappings ?? []),
            'field_mismatch' => false,
            'auto_generated_fields' => 0,
        ];

        if ($docusealData) {
            $apiFields = $docusealData['fields'] ?? $docusealData['schema'] ?? [];
            $result['api_field_count'] = count($apiFields);
            
            // Check for auto-generated field names
            foreach ($apiFields as $field) {
                $fieldName = $field['name'] ?? $field['id'] ?? '';
                if (preg_match('/^(field_\d+|text_\d+|input_\d+)$/i', $fieldName)) {
                    $result['auto_generated_fields']++;
                }
            }
            
            $result['field_mismatch'] = $result['api_field_count'] !== $result['db_field_count'];
        }

        return $result;
    }

    private function displaySummaryResults(array $results): void
    {
        $this->info('📊 Verification Summary');
        $this->newLine();

        $totalTemplates = count($results);
        $withApiData = count(array_filter($results, fn($r) => $r['has_api_data']));
        $withMismatches = count(array_filter($results, fn($r) => $r['field_mismatch']));
        $withAutoFields = count(array_filter($results, fn($r) => $r['auto_generated_fields'] > 0));

        $this->table([
            'Metric', 'Count', 'Percentage'
        ], [
            ['Total Templates', $totalTemplates, '100%'],
            ['With API Data', $withApiData, $this->percentage($withApiData, $totalTemplates)],
            ['Field Mismatches', $withMismatches, $this->percentage($withMismatches, $totalTemplates)],
            ['Auto-Generated Field Names', $withAutoFields, $this->percentage($withAutoFields, $totalTemplates)],
        ]);

        if ($withAutoFields > 0) {
            $this->newLine();
            $this->warn("⚠️  {$withAutoFields} templates have auto-generated field names that may not match PDF labels");
            $this->info("💡 Consider using OCR-based field detection for better accuracy");
        }
    }

    private function percentage(int $count, int $total): string
    {
        if ($total === 0) return '0%';
        return round(($count / $total) * 100, 1) . '%';
    }

    private function saveResultsToFile(array $results): void
    {
        $filename = 'docuseal_field_verification_' . now()->format('Y-m-d_H-i-s') . '.json';
        $path = storage_path('app/reports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT));
        $this->info("📄 Results saved to: {$path}");
    }
}

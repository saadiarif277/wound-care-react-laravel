<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TemplateIntelligenceService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\Http;

class ManualTemplateImportCommand extends Command
{
    protected $signature = 'docuseal:manual-import 
                           {--template-id= : Import specific template by ID}
                           {--from-list : Import from a predefined list of template IDs}
                           {--interactive : Interactive mode to input template IDs}';

    protected $description = 'Manually import Docuseal templates when folder API is not available';

    private TemplateIntelligenceService $templateIntelligence;

    public function __construct(TemplateIntelligenceService $templateIntelligence)
    {
        parent::__construct();
        $this->templateIntelligence = $templateIntelligence;
    }

    public function handle(): int
    {
        $this->info('📥 Manual Docuseal Template Import Tool');
        $this->newLine();

        if ($this->option('interactive')) {
            return $this->runInteractiveMode();
        }

        if ($this->option('template-id')) {
            return $this->importSingleTemplate($this->option('template-id'));
        }

        if ($this->option('from-list')) {
            return $this->importFromPredefinedList();
        }

        $this->showHelp();
        return self::SUCCESS;
    }

    private function runInteractiveMode(): int
    {
        $this->info('🔍 Interactive Template Import Mode');
        $this->line('Enter template IDs one by one. Press Enter with no input to finish.');
        $this->newLine();

        $templateIds = [];
        
        while (true) {
            $templateId = $this->ask('Template ID (or press Enter to finish)');
            
            if (empty($templateId)) {
                break;
            }
            
            $templateIds[] = trim($templateId);
        }

        if (empty($templateIds)) {
            $this->info('No template IDs provided.');
            return self::SUCCESS;
        }

        $this->info('📋 Importing ' . count($templateIds) . ' templates...');
        
        $successCount = 0;
        $errorCount = 0;

        foreach ($templateIds as $templateId) {
            try {
                $result = $this->importTemplate($templateId);
                if ($result) {
                    $successCount++;
                    $this->info("✅ Imported template: {$templateId}");
                } else {
                    $errorCount++;
                    $this->error("❌ Failed to import template: {$templateId}");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("❌ Error importing {$templateId}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("📊 Import Summary: {$successCount} successful, {$errorCount} failed");
        
        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function importSingleTemplate(string $templateId): int
    {
        $this->info("📥 Importing single template: {$templateId}");
        
        try {
            $result = $this->importTemplate($templateId);
            
            if ($result) {
                $this->info("✅ Template imported successfully");
                return self::SUCCESS;
            } else {
                $this->error("❌ Failed to import template");
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("❌ Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function importFromPredefinedList(): int
    {
        $this->info('📋 Importing from predefined template list...');
        
        // This would contain template IDs you've identified from your Docuseal account
        $predefinedTemplateIds = $this->getPredefinedTemplateList();
        
        if (empty($predefinedTemplateIds)) {
            $this->warn('⚠️  No predefined template IDs configured');
            $this->line('Add template IDs to the getPredefinedTemplateList() method');
            return self::SUCCESS;
        }

        $this->info('📊 Found ' . count($predefinedTemplateIds) . ' predefined template IDs');
        
        $successCount = 0;
        $errorCount = 0;

        foreach ($predefinedTemplateIds as $templateInfo) {
            $templateId = $templateInfo['id'];
            $expectedName = $templateInfo['name'] ?? 'Unknown';
            
            try {
                $this->line("📥 Importing: {$expectedName} ({$templateId})");
                
                $result = $this->importTemplate($templateId);
                if ($result) {
                    $successCount++;
                    $this->info("  ✅ Success");
                } else {
                    $errorCount++;
                    $this->error("  ❌ Failed");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("  ❌ Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("📊 Import Summary: {$successCount} successful, {$errorCount} failed");
        
        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function importTemplate(string $templateId): bool
    {
        // Fetch basic template info
        $templateData = $this->fetchTemplateBasicInfo($templateId);
        if (!$templateData) {
            return false;
        }

        // Fetch detailed template info
        $detailedTemplate = $this->fetchTemplateDetails($templateId);
        if (!$detailedTemplate) {
            return false;
        }

        // Use intelligent analysis to determine manufacturer and document type
        $analysis = $this->templateIntelligence->analyzeTemplate($templateData, $detailedTemplate);
        
        $manufacturer = $analysis['manufacturer'];
        $documentType = $analysis['document_type'];
        $confidenceScore = $analysis['confidence_score'];
        
        $this->line("  🧠 Analysis: {$confidenceScore}% confidence");
        $this->line("  🏭 Manufacturer: " . ($manufacturer?->name ?? 'Unknown'));
        $this->line("  📋 Document Type: {$documentType}");

        // Save to database
        $template = $this->saveTemplate($templateData, $detailedTemplate, $analysis);
        
        return $template !== null;
    }

    private function fetchTemplateBasicInfo(string $templateId): ?array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => config('docuseal.api_key'),
            ])->get(config('docuseal.api_url') . "/templates/{$templateId}");

            if (!$response->successful()) {
                $this->error("  ❌ Failed to fetch template info: HTTP " . $response->status());
                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            $this->error("  ❌ Exception fetching template: {$e->getMessage()}");
            return null;
        }
    }

    private function fetchTemplateDetails(string $templateId): ?array
    {
        // For now, use the same endpoint - in some APIs this might be different
        return $this->fetchTemplateBasicInfo($templateId);
    }

    private function saveTemplate(array $templateData, array $detailedTemplate, array $analysis): ?DocusealTemplate
    {
        try {
            $templateId = $templateData['id'];
            $templateName = $templateData['name'];
            $manufacturer = $analysis['manufacturer'];
            $documentType = $analysis['document_type'];

            // Use intelligent field mappings from analysis
            $fieldMappings = $analysis['field_mappings'] ?? [];

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
                        'import_method' => 'manual_import',
                        'analysis_confidence' => $analysis['confidence_score'],
                        'analysis_methods' => $analysis['analysis_methods'],
                        'import_date' => now()->toISOString(),
                    ],
                    'field_discovery_status' => 'completed',
                    'last_extracted_at' => now()
                ]
            );

            return $template;

        } catch (\Exception $e) {
            $this->error("  ❌ Database save failed: {$e->getMessage()}");
            return null;
        }
    }

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

        return !$existingDefault;
    }

    private function getPredefinedTemplateList(): array
    {
        // You can add known template IDs here based on your Docuseal account
        // This is where you'd put template IDs you've identified manually
        
        return [
            // Example format - replace with actual template IDs from your account
            ['id' => 'template_id_1', 'name' => 'ACZ IVR Form'],
            ['id' => 'template_id_2', 'name' => 'Integra Prior Authorization'],
            // Add more template IDs as you discover them
        ];
    }

    private function showHelp(): void
    {
        $this->info('📖 Manual Template Import Help');
        $this->newLine();
        
        $this->line('This command helps import Docuseal templates when the folder API is not available.');
        $this->newLine();
        
        $this->line('Usage Options:');
        $this->line('  --interactive     : Interactive mode to enter template IDs');
        $this->line('  --template-id=ID  : Import a specific template by ID');
        $this->line('  --from-list       : Import from predefined list (edit command to add IDs)');
        $this->newLine();
        
        $this->line('Examples:');
        $this->line('  php artisan docuseal:manual-import --interactive');
        $this->line('  php artisan docuseal:manual-import --template-id=abc123');
        $this->line('  php artisan docuseal:manual-import --from-list');
        $this->newLine();
        
        $this->line('To find template IDs:');
        $this->line('  1. Log into your Docuseal account');
        $this->line('  2. Go to Templates section');
        $this->line('  3. Copy template IDs from URLs or use browser developer tools');
        $this->line('  4. Run: php artisan docuseal:diagnose-api --verbose');
    }
}

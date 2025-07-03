<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\TemplateIntelligenceService;
use App\Models\Docuseal\DocusealTemplate;

class DiscoverAllTemplatesCommand extends Command
{
    protected $signature = 'docuseal:discover-all 
                           {--limit=100 : Number of templates per request (max 100)}
                           {--import : Import discovered templates with AI analysis}
                           {--folders= : Comma-separated list of folder names to search}';

    protected $description = 'Discover ALL Docuseal templates using proper API pagination and folder filtering';

    private TemplateIntelligenceService $templateIntelligence;

    public function __construct(TemplateIntelligenceService $templateIntelligence)
    {
        parent::__construct();
        $this->templateIntelligence = $templateIntelligence;
    }

    public function handle(): int
    {
        $this->info('🔍 Discovering ALL Docuseal Templates...');

        $apiKey = config('docuseal.api_key');
        $apiUrl = config('docuseal.api_url');

        if (!$apiKey || !$apiUrl) {
            $this->error('❌ Docuseal API configuration missing');
            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $shouldImport = $this->option('import');
        $folderNames = $this->option('folders') ? explode(',', $this->option('folders')) : [];

        $this->newLine();
        $this->info("📊 Settings: Limit={$limit}, Import={$shouldImport}, Folders=" . (empty($folderNames) ? 'All' : implode(', ', $folderNames)));

        $allTemplates = [];
        
        // Method 1: Get all templates with pagination
        $this->newLine();
        $this->info('📋 Method 1: Paginated Template Discovery...');
        $paginatedTemplates = $this->discoverTemplatesPaginated($apiKey, $apiUrl, $limit);
        $allTemplates = array_merge($allTemplates, $paginatedTemplates);

        // Method 2: Get templates by folder (if folders specified)
        if (!empty($folderNames)) {
            $this->newLine();
            $this->info('📁 Method 2: Folder-Based Template Discovery...');
            $folderTemplates = $this->discoverTemplatesByFolders($apiKey, $apiUrl, $folderNames, $limit);
            $allTemplates = array_merge($allTemplates, $folderTemplates);
        }

        // Method 3: Try common manufacturer folder names
        $this->newLine();
        $this->info('🏭 Method 3: Manufacturer Folder Discovery...');
        $manufacturerTemplates = $this->discoverManufacturerTemplates($apiKey, $apiUrl, $limit);
        $allTemplates = array_merge($allTemplates, $manufacturerTemplates);

        // Remove duplicates
        $uniqueTemplates = $this->removeDuplicateTemplates($allTemplates);

        $this->newLine();
        $this->info("🎉 Discovery Complete!");
        $this->info("📊 Total Unique Templates Found: " . count($uniqueTemplates));
        $this->line("📈 Raw Templates Discovered: " . count($allTemplates));
        $this->line("🔄 Duplicates Removed: " . (count($allTemplates) - count($uniqueTemplates)));

        // Display summary
        $this->displayTemplateSummary($uniqueTemplates);

        // Import if requested
        if ($shouldImport && !empty($uniqueTemplates)) {
            $this->newLine();
            if ($this->confirm('Import all discovered templates with AI analysis?')) {
                $this->importTemplates($uniqueTemplates);
            }
        }

        return self::SUCCESS;
    }

    private function discoverTemplatesPaginated(string $apiKey, string $apiUrl, int $limit): array
    {
        $templates = [];
        $after = null;
        $page = 1;

        do {
            $this->line("📄 Fetching page {$page} (limit: {$limit})...");
            
            $params = ['limit' => $limit];
            if ($after) {
                $params['after'] = $after;
            }

            try {
                $response = Http::withHeaders([
                    'X-Auth-Token' => $apiKey,
                ])->timeout(15)->get($apiUrl . '/templates', $params);

                if (!$response->successful()) {
                    $this->error("❌ API request failed: {$response->status()}");
                    break;
                }

                $data = $response->json();
                $pageTemplates = $data['data'] ?? $data ?? [];
                
                if (empty($pageTemplates)) {
                    $this->line("📄 Page {$page}: No templates found");
                    break;
                }

                $templates = array_merge($templates, $pageTemplates);
                $this->info("📄 Page {$page}: Found " . count($pageTemplates) . " templates");

                // Check for pagination
                $pagination = $data['pagination'] ?? null;
                $after = $pagination['next'] ?? null;
                
                if (!$after || count($pageTemplates) < $limit) {
                    break; // No more pages
                }

                $page++;

            } catch (\Exception $e) {
                $this->error("❌ Error on page {$page}: {$e->getMessage()}");
                break;
            }

        } while ($after && $page <= 20); // Safety limit

        $this->info("✅ Paginated discovery complete: " . count($templates) . " templates");
        return $templates;
    }

    private function discoverTemplatesByFolders(string $apiKey, string $apiUrl, array $folderNames, int $limit): array
    {
        $templates = [];

        foreach ($folderNames as $folderName) {
            $folderName = trim($folderName);
            $this->line("📁 Searching folder: {$folderName}");

            try {
                $response = Http::withHeaders([
                    'X-Auth-Token' => $apiKey,
                ])->timeout(10)->get($apiUrl . '/templates', [
                    'folder' => $folderName,
                    'limit' => $limit
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $folderTemplates = $data['data'] ?? $data ?? [];
                    
                    if (!empty($folderTemplates)) {
                        $templates = array_merge($templates, $folderTemplates);
                        $this->info("  ✅ Found " . count($folderTemplates) . " templates in '{$folderName}'");
                    } else {
                        $this->line("  📄 No templates in '{$folderName}'");
                    }
                } else {
                    $this->warn("  ⚠️  Failed to search folder '{$folderName}': {$response->status()}");
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Error searching folder '{$folderName}': {$e->getMessage()}");
            }
        }

        return $templates;
    }

    private function discoverManufacturerTemplates(string $apiKey, string $apiUrl, int $limit): array
    {
        $manufacturerFolders = [
            'ACZ', 'Integra', 'Kerecis', 'MiMedx', 'Organogenesis',
            'Smith & Nephew', 'StimLabs', 'Tissue Tech', 'BioWound',
            'BioWerX', 'AmnioBand', 'SKYE', 'Extremity Care',
            'Total Ancillary', 'Advanced Health', 'Default'
        ];

        $this->line("🏭 Trying " . count($manufacturerFolders) . " manufacturer folder names...");
        
        return $this->discoverTemplatesByFolders($apiKey, $apiUrl, $manufacturerFolders, $limit);
    }

    private function removeDuplicateTemplates(array $templates): array
    {
        $unique = [];
        $seen = [];

        foreach ($templates as $template) {
            $id = $template['id'] ?? null;
            if ($id && !isset($seen[$id])) {
                $unique[] = $template;
                $seen[$id] = true;
            }
        }

        return $unique;
    }

    private function displayTemplateSummary(array $templates): void
    {
        $this->newLine();
        $this->info('📊 Template Summary:');

        // Group by folder
        $byFolder = [];
        $bySource = [];

        foreach ($templates as $template) {
            $folderName = $template['folder_name'] ?? 'No Folder';
            $source = $template['source'] ?? 'unknown';
            
            $byFolder[$folderName] = ($byFolder[$folderName] ?? 0) + 1;
            $bySource[$source] = ($bySource[$source] ?? 0) + 1;
        }

        // Display folder breakdown
        $this->line('📁 By Folder:');
        foreach ($byFolder as $folder => $count) {
            $this->line("  {$folder}: {$count} templates");
        }

        // Display source breakdown  
        $this->line('🔧 By Source:');
        foreach ($bySource as $source => $count) {
            $this->line("  {$source}: {$count} templates");
        }

        // Show first few templates as examples
        $this->line('📄 Sample Templates:');
        foreach (array_slice($templates, 0, 5) as $template) {
            $name = $template['name'] ?? 'Unknown';
            $id = $template['id'] ?? 'Unknown';
            $folder = $template['folder_name'] ?? 'No Folder';
            $this->line("  📋 {$name} (ID: {$id}) - Folder: {$folder}");
        }

        if (count($templates) > 5) {
            $this->line("  ... and " . (count($templates) - 5) . " more templates");
        }
    }

    private function importTemplates(array $templates): void
    {
        $this->newLine();
        $this->info('🚀 Starting AI-Powered Template Import...');

        $successCount = 0;
        $errorCount = 0;

        foreach ($templates as $template) {
            $templateId = $template['id'];
            $templateName = $template['name'] ?? 'Unknown';
            
            $this->line("📋 Processing: {$templateName} (ID: {$templateId})");

            try {
                // Use intelligent analysis
                $analysis = $this->templateIntelligence->analyzeTemplate($template, $template);
                
                $manufacturer = $analysis['manufacturer'];
                $documentType = $analysis['document_type'];
                $confidenceScore = $analysis['confidence_score'];
                $fieldMappings = $analysis['field_mappings'] ?? [];

                $this->line("  🧠 Analysis: {$confidenceScore}% confidence");
                $this->line("  🏭 Manufacturer: " . ($manufacturer?->name ?? 'Unknown'));
                $this->line("  📋 Document Type: {$documentType}");

                // Save to database
                $savedTemplate = DocusealTemplate::updateOrCreate(
                    ['docuseal_template_id' => $templateId],
                    [
                        'template_name' => $templateName,
                        'manufacturer_id' => $manufacturer?->id,
                        'document_type' => $documentType,
                        'is_default' => $this->isDefaultTemplate($templateName, $manufacturer, $documentType),
                        'field_mappings' => $fieldMappings,
                        'is_active' => true,
                        'extraction_metadata' => [
                            'docuseal_created_at' => $template['created_at'] ?? null,
                            'docuseal_updated_at' => $template['updated_at'] ?? null,
                            'folder_name' => $template['folder_name'] ?? null,
                            'source' => $template['source'] ?? null,
                            'total_fields' => count($fieldMappings),
                            'import_method' => 'discover_all',
                            'analysis_confidence' => $analysis['confidence_score'],
                            'analysis_methods' => $analysis['analysis_methods'],
                            'import_date' => now()->toISOString(),
                        ],
                        'field_discovery_status' => 'completed',
                        'last_extracted_at' => now()
                    ]
                );

                $successCount++;
                $this->info("  ✅ Imported successfully");

            } catch (\Exception $e) {
                $errorCount++;
                $this->error("  ❌ Import failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("🎉 Import Complete!");
        $this->info("✅ Successfully imported: {$successCount}");
        if ($errorCount > 0) {
            $this->warn("❌ Failed imports: {$errorCount}");
        }
    }

    private function isDefaultTemplate(?string $templateName, $manufacturer, string $documentType): bool
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
}

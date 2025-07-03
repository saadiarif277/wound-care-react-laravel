<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiagnoseDocusealApiCommand extends Command
{
    protected $signature = 'docuseal:diagnose-api 
                           {--test-endpoints : Test all possible API endpoints}
                           {--show-details : Show detailed response data}';

    protected $description = 'Diagnose Docuseal API structure and find all available templates';

    public function handle(): int
    {
        $this->info('🔍 Diagnosing Docuseal API Structure...');

        $apiKey = config('docuseal.api_key');
        $apiUrl = config('docuseal.api_url');

        if (!$apiKey || !$apiUrl) {
            $this->error('❌ Docuseal API configuration missing');
            return self::FAILURE;
        }

        $this->info("📡 API URL: {$apiUrl}");
        $this->info("🔑 API Key: " . substr($apiKey, 0, 8) . '...');

        // Test basic connection
        $this->newLine();
        $this->info('🧪 Testing Basic API Endpoints...');

        $endpoints = [
            'Basic Templates' => '/templates',
            'Folders' => '/folders', 
            'Account Info' => '/account',
            'Submissions' => '/submissions',
        ];

        $workingEndpoints = [];
        $failedEndpoints = [];

        foreach ($endpoints as $name => $endpoint) {
            try {
                $response = Http::withHeaders([
                    'X-Auth-Token' => $apiKey,
                ])->timeout(10)->get($apiUrl . $endpoint);

                if ($response->successful()) {
                    $data = $response->json();
                    $count = is_array($data) ? count($data) : 'N/A';
                    
                    $this->info("✅ {$name}: {$response->status()} - {$count} items");
                    $workingEndpoints[$endpoint] = $data;
                    
                    if ($this->option('show-details')) {
                        $this->line("   Response: " . json_encode($data, JSON_PRETTY_PRINT));
                    }
                } else {
                    $this->warn("⚠️  {$name}: {$response->status()} - {$response->body()}");
                    $failedEndpoints[$endpoint] = $response->status();
                }
            } catch (\Exception $e) {
                $this->error("❌ {$name}: Exception - {$e->getMessage()}");
                $failedEndpoints[$endpoint] = $e->getMessage();
            }
        }

        // Analyze templates
        if (isset($workingEndpoints['/templates'])) {
            $this->newLine();
            $this->info('📋 Analyzing Available Templates...');
            
            $response = $workingEndpoints['/templates'];
            $templates = $response['data'] ?? $response; // Handle both data array and direct array
            
            if (empty($templates)) {
                $this->warn('⚠️  No templates found in /templates endpoint');
            } else {
                $this->info("📊 Found " . count($templates) . " templates via /templates endpoint");
                
                // Show pagination info if available
                if (isset($response['pagination'])) {
                    $pagination = $response['pagination'];
                    $this->line("📄 Pagination: Count={$pagination['count']}, Next={$pagination['next']}, Prev={$pagination['prev']}");
                }
                
                foreach ($templates as $template) {
                    $name = $template['name'] ?? 'Unknown';
                    $id = $template['id'] ?? 'Unknown';
                    $createdAt = $template['created_at'] ?? 'Unknown';
                    $folderName = $template['folder_name'] ?? 'No Folder';
                    
                    $this->line("  📄 {$name} (ID: {$id}) - Folder: {$folderName} - Created: {$createdAt}");
                }
            }
        }

        // Test folder discovery alternatives
        $this->newLine();
        $this->info('🔍 Testing Alternative Template Discovery Methods...');

        if ($this->option('test-endpoints')) {
            $this->testAlternativeEndpoints($apiKey, $apiUrl);
        }

        // Check if we can get template details
        if (isset($workingEndpoints['/templates']) && !empty($workingEndpoints['/templates'])) {
            $this->newLine();
            $this->info('🔬 Testing Template Detail Retrieval...');
            
            $templates = $workingEndpoints['/templates'];
            
            // Handle different possible array structures
            $firstTemplate = null;
            $templateId = null;
            
            try {
                if (is_array($templates) && count($templates) > 0) {
                    // Try to get first element safely using foreach to avoid index issues
                    foreach ($templates as $template) {
                        $firstTemplate = $template;
                        $templateId = $template['id'] ?? null;
                        break; // Just get the first one
                    }
                }
            } catch (\Exception $e) {
                $this->warn('⚠️  Error accessing template structure: ' . $e->getMessage());
            }
            
            if (!$firstTemplate || !$templateId) {
                $this->warn('⚠️  Unable to access template structure for detailed analysis');
                if ($this->option('show-details')) {
                    $this->line('Templates data structure: ' . json_encode($templates, JSON_PRETTY_PRINT));
                }
            } else {
                $this->line("📋 Testing with template ID: {$templateId}");
                
                if ($templateId) {
                    try {
                        $response = Http::withHeaders([
                            'X-Auth-Token' => $apiKey,
                        ])->get($apiUrl . "/templates/{$templateId}");

                        if ($response->successful()) {
                            $detailedTemplate = $response->json();
                            $this->info("✅ Template details accessible for ID: {$templateId}");
                            
                            $fields = $detailedTemplate['fields'] ?? $detailedTemplate['schema'] ?? [];
                            $this->line("  📝 Found " . count($fields) . " fields in template");
                            
                            if ($this->option('show-details') && !empty($fields)) {
                                $this->line("  Fields:");
                                foreach (array_slice($fields, 0, 5) as $field) {
                                    $fieldName = $field['name'] ?? $field['id'] ?? 'Unknown';
                                    $fieldType = $field['type'] ?? 'unknown';
                                    $this->line("    - {$fieldName} ({$fieldType})");
                                }
                                if (count($fields) > 5) {
                                    $this->line("    ... and " . (count($fields) - 5) . " more fields");
                                }
                            }
                        } else {
                            $this->warn("⚠️  Cannot retrieve template details: {$response->status()}");
                        }
                    } catch (\Exception $e) {
                        $this->error("❌ Error retrieving template details: {$e->getMessage()}");
                    }
                }
            }
        }

        // Recommendations
        $this->newLine();
        $this->info('💡 Recommendations:');

        if (empty($workingEndpoints['/templates'])) {
            $this->error('❌ CRITICAL: No templates accessible via API');
            $this->line('   - Check API key permissions');
            $this->line('   - Verify account has templates');
            $this->line('   - Contact Docuseal support');
        } elseif (count($workingEndpoints['/templates']) < 5) {
            $this->warn('⚠️  LIMITED: Only ' . count($workingEndpoints['/templates']) . ' templates found');
            $this->line('   - Templates may be organized in folders');
            $this->line('   - Folder API endpoint not working (/folders returned ' . ($failedEndpoints['/folders'] ?? 'error') . ')');
            $this->line('   - Consider manual template organization in Docuseal');
            $this->line('   - Alternative: Move templates to top level for API access');
        } else {
            $this->info('✅ GOOD: Multiple templates accessible');
        }

        if (!isset($workingEndpoints['/folders']) || empty($workingEndpoints['/folders'])) {
            $this->warn('⚠️  FOLDER ISSUE: Folder endpoint not working or no folders');
            $this->line('   - This explains why only top-level templates are synced');
            $this->line('   - Consider these solutions:');
            $this->line('     1. Move all templates to Docuseal root level (not in folders)');
            $this->line('     2. Use template naming conventions instead of folders');
            $this->line('     3. Manual template organization in our database');
        }

        return self::SUCCESS;
    }

    private function testAlternativeEndpoints(string $apiKey, string $apiUrl): void
    {
        $alternativeEndpoints = [
            'Template Search' => '/templates?search=',
            'All Resources' => '/resources',
            'Template Categories' => '/template_categories',
            'Organizations' => '/organizations',
            'Workspaces' => '/workspaces',
        ];

        foreach ($alternativeEndpoints as $name => $endpoint) {
            try {
                $response = Http::withHeaders([
                    'X-Auth-Token' => $apiKey,
                ])->timeout(5)->get($apiUrl . $endpoint);

                if ($response->successful()) {
                    $data = $response->json();
                    $count = is_array($data) ? count($data) : 'object';
                    $this->info("  ✅ {$name}: {$count} items");
                } else {
                    $this->line("  ❌ {$name}: {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->line("  ❌ {$name}: Exception");
            }
        }
    }
}

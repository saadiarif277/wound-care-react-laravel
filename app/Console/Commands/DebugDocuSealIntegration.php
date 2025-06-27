<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use App\Services\DocusealService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DebugDocuSealIntegration extends Command
{
    protected $signature = 'docuseal:debug 
                            {--manufacturer= : Specific manufacturer ID to debug}
                            {--product= : Specific product code to debug}
                            {--templates : Show template configuration details}
                            {--mappings : Show field mapping details}
                            {--api : Test API connectivity}
                            {--fix : Attempt to fix common issues}';

    protected $description = 'Debug DocuSeal integration issues including template mappings and manufacturer configurations';

    public function handle()
    {
        $this->info('🔍 DocuSeal Integration Diagnostic Tool');
        $this->info('=====================================');

        // Run all checks by default, or specific ones based on options
        $this->checkOverallHealth();
        
        if ($this->option('templates') || !$this->hasSpecificOptions()) {
            $this->checkTemplateConfiguration();
        }
        
        if ($this->option('mappings') || !$this->hasSpecificOptions()) {
            $this->checkFieldMappings();
        }
        
        if ($this->option('api') || !$this->hasSpecificOptions()) {
            $this->testApiConnectivity();
        }
        
        $this->checkManufacturerProductRelationships();
        
        if ($manufacturerId = $this->option('manufacturer')) {
            $this->debugSpecificManufacturer($manufacturerId);
        }
        
        if ($productCode = $this->option('product')) {
            $this->debugSpecificProduct($productCode);
        }
        
        if ($this->option('fix')) {
            $this->attemptCommonFixes();
        }
        
        $this->showRecommendations();
        
        return 0;
    }

    private function hasSpecificOptions(): bool
    {
        return $this->option('templates') || 
               $this->option('mappings') || 
               $this->option('api') ||
               $this->option('manufacturer') ||
               $this->option('product');
    }

    private function checkOverallHealth()
    {
        $this->info("\n📊 Overall System Health");
        $this->line('─────────────────────');
        
        // Count totals
        $totalManufacturers = Manufacturer::count();
        $totalProducts = Product::count();
        $totalTemplates = DocusealTemplate::count();
        $activeTemplates = DocusealTemplate::where('is_active', true)->count();
        $ivrTemplates = DocusealTemplate::where('document_type', 'IVR')->count();
        
        $this->table([
            'Metric',
            'Count',
            'Status'
        ], [
            ['Total Manufacturers', $totalManufacturers, $totalManufacturers > 0 ? '✅' : '❌'],
            ['Total Products', $totalProducts, $totalProducts > 0 ? '✅' : '❌'],
            ['Total DocuSeal Templates', $totalTemplates, $totalTemplates > 0 ? '✅' : '❌'],
            ['Active Templates', $activeTemplates, $activeTemplates > 0 ? '✅' : '❌'],
            ['IVR Templates', $ivrTemplates, $ivrTemplates > 0 ? '✅' : '❌'],
        ]);
    }

    private function checkTemplateConfiguration()
    {
        $this->info("\n🗂️ Template Configuration Analysis");
        $this->line('──────────────────────────────');
        
        // Check for manufacturers without templates
        $manufacturersWithoutTemplates = DB::select("
            SELECT 
                m.id,
                m.name,
                COUNT(dt.id) as template_count
            FROM manufacturers m
            LEFT JOIN docuseal_templates dt ON m.id = dt.manufacturer_id 
                AND dt.document_type = 'IVR' 
                AND dt.is_active = true
            GROUP BY m.id, m.name
            HAVING template_count = 0
        ");
        
        if (!empty($manufacturersWithoutTemplates)) {
            $this->warn("⚠️ Manufacturers without IVR templates:");
            $tableData = [];
            foreach ($manufacturersWithoutTemplates as $manufacturer) {
                $tableData[] = [$manufacturer->id, $manufacturer->name];
            }
            $this->table(['ID', 'Manufacturer Name'], $tableData);
        } else {
            $this->info("✅ All manufacturers have IVR templates");
        }
        
        // Check for templates without default flag
        $templatesWithoutDefault = DocusealTemplate::where('document_type', 'IVR')
            ->where('is_active', true)
            ->where('is_default', false)
            ->with('manufacturer')
            ->get();
            
        if ($templatesWithoutDefault->count() > 0) {
            $this->warn("⚠️ Active IVR templates that are not marked as default:");
            $tableData = [];
            foreach ($templatesWithoutDefault as $template) {
                $tableData[] = [
                    $template->id,
                    $template->manufacturer->name ?? 'Unknown',
                    $template->template_name,
                    $template->docuseal_template_id
                ];
            }
            $this->table(['ID', 'Manufacturer', 'Template Name', 'DocuSeal ID'], $tableData);
        }
        
        // Show template summary
        $templateSummary = DB::select("
            SELECT 
                m.name as manufacturer_name,
                dt.template_name,
                dt.docuseal_template_id,
                dt.is_active,
                dt.is_default,
                CASE 
                    WHEN dt.field_mappings IS NULL THEN 0
                    WHEN JSON_TYPE(dt.field_mappings) = 'ARRAY' THEN JSON_LENGTH(dt.field_mappings)
                    WHEN JSON_TYPE(dt.field_mappings) = 'OBJECT' THEN (
                        SELECT COUNT(*) FROM JSON_TABLE(dt.field_mappings, '$.*' COLUMNS (dummy INT PATH '$')) AS jt
                    )
                    ELSE 0
                END as field_mapping_count
            FROM docuseal_templates dt
            LEFT JOIN manufacturers m ON dt.manufacturer_id = m.id
            WHERE dt.document_type = 'IVR'
            ORDER BY m.name, dt.template_name
        ");
        
        if (!empty($templateSummary)) {
            $this->info("\n📋 Template Configuration Summary:");
            $tableData = [];
            foreach ($templateSummary as $template) {
                $tableData[] = [
                    $template->manufacturer_name ?? 'Unknown',
                    $template->template_name,
                    $template->docuseal_template_id,
                    $template->is_active ? '✅' : '❌',
                    $template->is_default ? '✅' : '❌',
                    $template->field_mapping_count
                ];
            }
            $this->table([
                'Manufacturer', 
                'Template Name', 
                'DocuSeal ID', 
                'Active', 
                'Default', 
                'Field Mappings'
            ], $tableData);
        }
    }

    private function checkFieldMappings()
    {
        $this->info("\n🗺️ Field Mapping Analysis");
        $this->line('─────────────────────');
        
        $templatesWithoutMappings = DocusealTemplate::where('document_type', 'IVR')
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('field_mappings')
                      ->orWhere('field_mappings', '[]')
                      ->orWhere('field_mappings', '{}');
            })
            ->with('manufacturer')
            ->get();
            
        if ($templatesWithoutMappings->count() > 0) {
            $this->warn("⚠️ Templates without field mappings:");
            $tableData = [];
            foreach ($templatesWithoutMappings as $template) {
                $tableData[] = [
                    $template->manufacturer->name ?? 'Unknown',
                    $template->template_name,
                    $template->docuseal_template_id
                ];
            }
            $this->table(['Manufacturer', 'Template Name', 'DocuSeal ID'], $tableData);
        } else {
            $this->info("✅ All active IVR templates have field mappings");
        }
        
        // Show sample field mappings
        $templateWithMappings = DocusealTemplate::where('document_type', 'IVR')
            ->where('is_active', true)
            ->whereNotNull('field_mappings')
            ->where('field_mappings', '!=', '[]')
            ->where('field_mappings', '!=', '{}')
            ->with('manufacturer')
            ->first();
            
        if ($templateWithMappings) {
            $manufacturerName = $templateWithMappings->manufacturer->name ?? 'Unknown';
            $this->info("\n📝 Sample Field Mapping Structure (from {$manufacturerName}):");
            $mappings = $templateWithMappings->field_mappings;
            if (is_array($mappings)) {
                foreach (array_slice($mappings, 0, 5) as $key => $value) {
                    $displayValue = is_array($value) ? json_encode($value) : (string)$value;
                    $this->line("  {$key} → {$displayValue}");
                }
                if (count($mappings) > 5) {
                    $this->line("  ... and " . (count($mappings) - 5) . " more mappings");
                }
            }
        }
    }

    private function testApiConnectivity()
    {
        $this->info("\n🌐 DocuSeal API Connectivity Test");
        $this->line('────────────────────────────');
        
        $apiKey = config('docuseal.api_key');
        $apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');
        
        if (!$apiKey) {
            $this->error("❌ DocuSeal API key not configured");
            return;
        }
        
        $this->info("API URL: {$apiUrl}");
        $this->info("API Key: " . substr($apiKey, 0, 8) . '...');
        
        try {
            $docusealService = app(DocusealService::class);
            
            // Test basic connectivity
            $this->line("Testing API connectivity...");
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Auth-Token' => $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(10)->get("{$apiUrl}/templates");
            
            if ($response->successful()) {
                $this->info("✅ API connectivity successful");
                $templates = $response->json();
                $this->info("📄 Found " . count($templates) . " templates in DocuSeal account");
            } else {
                $this->error("❌ API connectivity failed: " . $response->status() . " - " . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error("❌ API test exception: " . $e->getMessage());
        }
    }

    private function checkManufacturerProductRelationships()
    {
        $this->info("\n🔗 Manufacturer-Product Relationships");
        $this->line('───────────────────────────────────');
        
        // Check for products without manufacturers
        $productsWithoutManufacturers = Product::whereNull('manufacturer_id')->count();
        
        if ($productsWithoutManufacturers > 0) {
            $this->warn("⚠️ {$productsWithoutManufacturers} products without manufacturer assignments");
        } else {
            $this->info("✅ All products have manufacturer assignments");
        }
        
        // Show manufacturer-product distribution
        $manufacturerProductCounts = DB::select("
            SELECT 
                m.id,
                m.name,
                COUNT(p.id) as product_count,
                COUNT(dt.id) as template_count
            FROM manufacturers m
            LEFT JOIN products p ON m.id = p.manufacturer_id
            LEFT JOIN docuseal_templates dt ON m.id = dt.manufacturer_id 
                AND dt.document_type = 'IVR' 
                AND dt.is_active = true
            GROUP BY m.id, m.name
            ORDER BY product_count DESC
            LIMIT 10
        ");
        
        if (!empty($manufacturerProductCounts)) {
            $this->info("\n📊 Top Manufacturers by Product Count:");
            $tableData = [];
            foreach ($manufacturerProductCounts as $manufacturer) {
                $status = $manufacturer->template_count > 0 ? '✅' : '❌';
                $tableData[] = [
                    $manufacturer->name,
                    $manufacturer->product_count,
                    $manufacturer->template_count,
                    $status
                ];
            }
            $this->table(['Manufacturer', 'Products', 'Templates', 'Status'], $tableData);
        }
    }

    private function debugSpecificManufacturer($manufacturerId)
    {
        $this->info("\n🔍 Debugging Manufacturer ID: {$manufacturerId}");
        $this->line('─────────────────────────────────────────');
        
        $manufacturer = Manufacturer::find($manufacturerId);
        if (!$manufacturer) {
            $this->error("❌ Manufacturer not found");
            return;
        }
        
        $this->info("Manufacturer: {$manufacturer->name}");
        
        // Check products
        $products = Product::where('manufacturer_id', $manufacturerId)->get();
        $this->info("Products: " . $products->count());
        
        // Check templates
        $templates = DocusealTemplate::where('manufacturer_id', $manufacturerId)
            ->where('document_type', 'IVR')
            ->get();
            
        if ($templates->count() > 0) {
            $this->info("IVR Templates:");
            foreach ($templates as $template) {
                $status = ($template->is_active && $template->is_default) ? '✅' : '⚠️';
                $this->line("  {$status} {$template->template_name} (ID: {$template->docuseal_template_id})");
                $this->line("    Active: " . ($template->is_active ? 'Yes' : 'No'));
                $this->line("    Default: " . ($template->is_default ? 'Yes' : 'No'));
                $this->line("    Field Mappings: " . (is_array($template->field_mappings) ? count($template->field_mappings) : 0));
            }
        } else {
            $this->error("❌ No IVR templates found for this manufacturer");
        }
    }

    private function debugSpecificProduct($productCode)
    {
        $this->info("\n🔍 Debugging Product Code: {$productCode}");
        $this->line('────────────────────────────────────');
        
        $product = Product::with('manufacturer')->where('q_code', $productCode)->first();
        if (!$product) {
            $this->error("❌ Product not found");
            return;
        }
        
        $this->info("Product: {$product->name}");
        $manufacturerId = $product->manufacturer_id ?? 'NULL';
        $this->info("Manufacturer ID: {$manufacturerId}");
        
        if ($product->manufacturer_id) {
            // Load the manufacturer relationship manually to avoid conflicts with the manufacturer string field
            $manufacturer = \App\Models\Order\Manufacturer::find($product->manufacturer_id);
            if ($manufacturer) {
                $this->info("Manufacturer: {$manufacturer->name}");
                
                // Check if manufacturer has templates
                $template = DocusealTemplate::getDefaultTemplateForManufacturer($product->manufacturer_id, 'IVR');
                if ($template) {
                    $this->info("✅ Default IVR template found: {$template->template_name}");
                } else {
                    $this->error("❌ No default IVR template found for this manufacturer");
                }
            } else {
                $this->error("❌ Manufacturer ID {$product->manufacturer_id} not found in database");
            }
        } else {
            $this->error("❌ Product has no manufacturer assigned");
        }
    }

    private function attemptCommonFixes()
    {
        $this->info("\n🔧 Attempting Common Fixes");
        $this->line('─────────────────────');
        
        if (!$this->confirm('This will modify your database. Are you sure you want to continue?')) {
            $this->info("Fix operation cancelled.");
            return;
        }
        
        $fixes = 0;
        
        // Fix 1: Set first active template as default for manufacturers without defaults
        $manufacturersNeedingDefaults = DB::select("
            SELECT DISTINCT m.id, m.name
            FROM manufacturers m
            JOIN docuseal_templates dt ON m.id = dt.manufacturer_id
            WHERE dt.document_type = 'IVR' 
            AND dt.is_active = true
            AND m.id NOT IN (
                SELECT DISTINCT manufacturer_id 
                FROM docuseal_templates 
                WHERE document_type = 'IVR' 
                AND is_active = true 
                AND is_default = true
            )
        ");
        
        foreach ($manufacturersNeedingDefaults as $manufacturer) {
            $firstTemplate = DocusealTemplate::where('manufacturer_id', $manufacturer->id)
                ->where('document_type', 'IVR')
                ->where('is_active', true)
                ->first();
                
            if ($firstTemplate) {
                $firstTemplate->update(['is_default' => true]);
                $this->info("✅ Set default template for {$manufacturer->name}");
                $fixes++;
            }
        }
        
        // Fix 2: Activate templates that are marked as default but inactive
        $inactiveDefaults = DocusealTemplate::where('document_type', 'IVR')
            ->where('is_default', true)
            ->where('is_active', false)
            ->update(['is_active' => true]);
            
        if ($inactiveDefaults > 0) {
            $this->info("✅ Activated {$inactiveDefaults} default templates");
            $fixes += $inactiveDefaults;
        }
        
        $this->info("🎉 Applied {$fixes} fixes");
    }

    private function showRecommendations()
    {
        $this->info("\n💡 Recommendations");
        $this->line('─────────────────');
        
        $recommendations = [];
        
        // Check for common issues and provide recommendations
        $manufacturersWithoutTemplates = Manufacturer::whereDoesntHave('docusealTemplates', function($query) {
            $query->where('document_type', 'IVR')->where('is_active', true);
        })->count();
        
        if ($manufacturersWithoutTemplates > 0) {
            $recommendations[] = "Create IVR templates for {$manufacturersWithoutTemplates} manufacturers without them";
        }
        
        $templatesWithoutMappings = DocusealTemplate::where('document_type', 'IVR')
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('field_mappings')
                      ->orWhere('field_mappings', '[]')
                      ->orWhere('field_mappings', '{}');
            })->count();
            
        if ($templatesWithoutMappings > 0) {
            $recommendations[] = "Configure field mappings for {$templatesWithoutMappings} templates";
        }
        
        if (empty($recommendations)) {
            $this->info("🎉 No major issues detected!");
        } else {
            foreach ($recommendations as $recommendation) {
                $this->line("• {$recommendation}");
            }
        }
        
        $this->info("\n📚 Additional Commands:");
        $this->line("• php artisan docuseal:debug --manufacturer=X  - Debug specific manufacturer");
        $this->line("• php artisan docuseal:debug --product=CODE    - Debug specific product");
        $this->line("• php artisan docuseal:debug --fix             - Attempt automatic fixes");
        $this->line("• php artisan docuseal:debug --api             - Test API connectivity only");
    }
}

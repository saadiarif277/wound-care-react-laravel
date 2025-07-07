<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order\Manufacturer;
use App\Models\PDF\ManufacturerPdfTemplate;
use App\Services\PDF\AzurePDFStorageService;
use App\Services\PDF\PDFMappingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IVRManagementCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ivr:manage 
                           {action : The action to perform (list|upload|report|init)}
                           {manufacturer? : The manufacturer name (required for upload)}
                           {file? : The file path (required for upload)}
                           {--version=1.0 : Version number for the IVR form}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage IVR forms by manufacturer';

    protected AzurePDFStorageService $storageService;
    protected PDFMappingService $pdfMappingService;

    public function __construct(
        AzurePDFStorageService $storageService,
        PDFMappingService $pdfMappingService
    ) {
        parent::__construct();
        $this->storageService = $storageService;
        $this->pdfMappingService = $pdfMappingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listIVRForms(),
            'upload' => $this->uploadIVRForm(),
            'report' => $this->generateReport(),
            'init' => $this->initializeDirectories(),
            default => $this->error("Invalid action: {$action}. Use list, upload, report, or init.")
        };
    }

    /**
     * List all IVR forms organized by manufacturer
     */
    protected function listIVRForms()
    {
        $this->info('📋 IVR Forms by Manufacturer');
        $this->line('');

        $manufacturers = Manufacturer::with(['pdfTemplates' => function ($query) {
            $query->where('document_type', 'ivr')
                  ->where('is_active', true);
        }])
        ->orderBy('name')
        ->get();

        $headers = ['Manufacturer', 'IVR Template', 'Version', 'Status', 'Uploaded'];
        $rows = [];

        foreach ($manufacturers as $manufacturer) {
            $template = $manufacturer->pdfTemplates->where('document_type', 'ivr')->first();
            
            if ($template) {
                $rows[] = [
                    $manufacturer->name,
                    $template->template_name,
                    $template->version,
                    $template->is_active ? '✅ Active' : '❌ Inactive',
                    $template->created_at->format('Y-m-d H:i')
                ];
            } else {
                $rows[] = [
                    $manufacturer->name,
                    '❌ No IVR Template',
                    '-',
                    '-',
                    '-'
                ];
            }
        }

        $this->table($headers, $rows);

        // Show directory structure
        $this->line('');
        $this->info('📁 Directory Structure:');
        $this->showDirectoryStructure();

        return 0;
    }

    /**
     * Upload an IVR form for a specific manufacturer
     */
    protected function uploadIVRForm()
    {
        $manufacturerName = $this->argument('manufacturer');
        $filePath = $this->argument('file');

        if (!$manufacturerName || !$filePath) {
            $this->error('Both manufacturer and file path are required for upload.');
            return 1;
        }

        // Find manufacturer
        $manufacturer = Manufacturer::where('name', 'like', "%{$manufacturerName}%")
                                   ->orWhere('slug', Str::slug($manufacturerName))
                                   ->first();

        if (!$manufacturer) {
            $this->error("Manufacturer '{$manufacturerName}' not found.");
            $this->line('Available manufacturers:');
            $this->showAvailableManufacturers();
            return 1;
        }

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        // Validate file
        $fileInfo = pathinfo($filePath);
        if (strtolower($fileInfo['extension']) !== 'pdf') {
            $this->error('Only PDF files are supported.');
            return 1;
        }

        $this->info("📤 Uploading IVR form for {$manufacturer->name}...");

        try {
            // Generate template name
            $templateName = "{$manufacturer->name} IVR";
            $version = $this->option('version');
            $fileName = Str::slug($templateName) . "-{$version}-" . time() . '.pdf';
            $storagePath = "pdf-templates/{$manufacturer->id}/{$fileName}";

            // Upload to storage
            $uploadResult = $this->storageService->uploadTemplate(
                $filePath,
                $storagePath,
                [
                    'manufacturer_id' => $manufacturer->id,
                    'document_type' => 'ivr',
                    'version' => $version,
                ]
            );

            if (!$uploadResult['success']) {
                $this->error("Upload failed: " . ($uploadResult['error'] ?? 'Unknown error'));
                return 1;
            }

            // Extract form fields
            $extractedFields = $this->pdfMappingService->extractFormFields($filePath);

            // Deactivate existing IVR templates
            ManufacturerPdfTemplate::where('manufacturer_id', $manufacturer->id)
                                   ->where('document_type', 'ivr')
                                   ->update(['is_active' => false]);

            // Create new template record
            $template = ManufacturerPdfTemplate::create([
                'manufacturer_id' => $manufacturer->id,
                'template_name' => $templateName,
                'document_type' => 'ivr',
                'file_path' => $storagePath,
                'azure_container' => 'pdf-templates',
                'version' => $version,
                'is_active' => true,
                'template_fields' => $extractedFields,
                'metadata' => [
                    'uploaded_by' => 'console',
                    'uploaded_at' => now()->toIso8601String(),
                    'file_size' => filesize($filePath),
                    'field_count' => count($extractedFields),
                    'original_filename' => basename($filePath),
                ],
            ]);

            $this->info("✅ Successfully uploaded IVR form for {$manufacturer->name}");
            $this->line("   Template ID: {$template->id}");
            $this->line("   Version: {$version}");
            $this->line("   Fields found: " . count($extractedFields));
            $this->line("   File size: " . number_format(filesize($filePath) / 1024 / 1024, 2) . ' MB');

            return 0;

        } catch (\Exception $e) {
            $this->error("Upload failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Generate a comprehensive report of IVR forms
     */
    protected function generateReport()
    {
        $this->info('📊 IVR Forms Report');
        $this->line('');

        $manufacturers = Manufacturer::count();
        $totalTemplates = ManufacturerPdfTemplate::where('document_type', 'ivr')->count();
        $activeTemplates = ManufacturerPdfTemplate::where('document_type', 'ivr')
                                                 ->where('is_active', true)
                                                 ->count();
        $manufacturersWithIVR = Manufacturer::whereHas('pdfTemplates', function ($query) {
            $query->where('document_type', 'ivr')->where('is_active', true);
        })->count();

        $this->table(['Metric', 'Count'], [
            ['Total Manufacturers', $manufacturers],
            ['Total IVR Templates', $totalTemplates],
            ['Active IVR Templates', $activeTemplates],
            ['Manufacturers with IVR', $manufacturersWithIVR],
            ['Coverage Percentage', round(($manufacturersWithIVR / $manufacturers) * 100, 2) . '%'],
        ]);

        $this->line('');
        $this->info('📈 Manufacturers without IVR forms:');
        
        $manufacturersWithoutIVR = Manufacturer::whereDoesntHave('pdfTemplates', function ($query) {
            $query->where('document_type', 'ivr')->where('is_active', true);
        })->orderBy('name')->get();

        foreach ($manufacturersWithoutIVR as $manufacturer) {
            $this->line("   ❌ {$manufacturer->name}");
        }

        return 0;
    }

    /**
     * Initialize directory structure for all manufacturers
     */
    protected function initializeDirectories()
    {
        $this->info('🏗️ Initializing IVR directory structure...');

        $manufacturers = Manufacturer::orderBy('name')->get();
        $baseDir = 'ivr-forms';

        foreach ($manufacturers as $manufacturer) {
            $manufacturerDir = $baseDir . '/' . Str::slug($manufacturer->name, '_');
            
            // Create subdirectories
            $subdirs = ['current', 'archived', 'templates'];
            foreach ($subdirs as $subdir) {
                $fullPath = $manufacturerDir . '/' . $subdir;
                Storage::makeDirectory($fullPath);
            }

            // Create manufacturer-specific README
            $readmePath = $manufacturerDir . '/README.md';
            $readmeContent = $this->generateManufacturerReadme($manufacturer);
            Storage::put($readmePath, $readmeContent);

            $this->line("   ✅ Created directory for {$manufacturer->name}");
        }

        $this->info("✅ Directory structure initialized for {$manufacturers->count()} manufacturers");
        return 0;
    }

    /**
     * Show directory structure
     */
    protected function showDirectoryStructure()
    {
        $baseDir = storage_path('app/ivr-forms');
        
        if (!is_dir($baseDir)) {
            $this->warn('IVR forms directory not found. Run: php artisan ivr:manage init');
            return;
        }

        $directories = glob($baseDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $manufacturerName = basename($dir);
            $this->line("   📁 {$manufacturerName}");
            
            // Show subdirectories if they exist
            $subdirs = glob($dir . '/*', GLOB_ONLYDIR);
            foreach ($subdirs as $subdir) {
                $subdirName = basename($subdir);
                $this->line("      📁 {$subdirName}");
            }
        }
    }

    /**
     * Show available manufacturers
     */
    protected function showAvailableManufacturers()
    {
        $manufacturers = Manufacturer::orderBy('name')->get();
        
        foreach ($manufacturers as $manufacturer) {
            $this->line("   • {$manufacturer->name} (slug: {$manufacturer->slug})");
        }
    }

    /**
     * Generate manufacturer-specific README content
     */
    protected function generateManufacturerReadme(Manufacturer $manufacturer): string
    {
        return "# {$manufacturer->name} - IVR Forms

## Manufacturer Information
- **Name**: {$manufacturer->name}
- **Contact Email**: {$manufacturer->contact_email}
- **Contact Phone**: {$manufacturer->contact_phone}
- **Website**: {$manufacturer->website}

## Directory Structure
- `current/` - Active IVR forms currently in use
- `archived/` - Previous versions of IVR forms
- `templates/` - Template files and drafts

## File Naming Convention
- Current: `{$manufacturer->name}_IVR_v{version}.pdf`
- Archived: `{$manufacturer->name}_IVR_v{version}_{date}.pdf`

## Upload Instructions
1. Save the IVR form to the `current/` directory
2. Move the previous version to `archived/`
3. Upload via admin panel or CLI command:
   ```bash
   php artisan ivr:manage upload \"{$manufacturer->name}\" \"/path/to/file.pdf\"
   ```

## Integration Notes
- Forms are automatically processed for field extraction
- ML system learns from field mappings
- Templates are used in Quick Request workflow

## Last Updated
" . now()->format('Y-m-d H:i:s') . "
";
    }
} 
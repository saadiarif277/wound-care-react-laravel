<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocusealService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestDocusealApiCommand extends Command
{
    protected $signature = 'test:docuseal-api
                           {--test-auth : Test authentication only}
                           {--test-templates : Test template fetching}
                           {--test-submission : Test submission creation}
                           {--manufacturer= : Test with specific manufacturer ID}
                           {--show-config : Show current configuration}';

    protected $description = 'Test Docuseal API integration and diagnose issues';

    protected DocusealService $docuSealService;

    public function handle(): int
    {
        $this->docuSealService = app(DocusealService::class);

        $this->info('🔍 Testing Docuseal API Integration');
        $this->newLine();

        if ($this->option('show-config')) {
            $this->showConfiguration();
            return self::SUCCESS;
        }

        $success = true;

        // Test 1: Configuration
        $this->info('📋 Step 1: Checking Configuration...');
        if (!$this->testConfiguration()) {
            $success = false;
        }
        $this->newLine();

        // Test 2: Authentication
        $this->info('🔐 Step 2: Testing Authentication...');
        if (!$this->testAuthentication()) {
            $success = false;
        }
        $this->newLine();

        if ($this->option('test-auth')) {
            return $success ? self::SUCCESS : self::FAILURE;
        }

        // Test 3: Templates
        if ($this->option('test-templates') || !$this->option('test-submission')) {
            $this->info('📄 Step 3: Testing Template Access...');
            if (!$this->testTemplates()) {
                $success = false;
            }
            $this->newLine();
        }

        // Test 4: Submission Creation
        if ($this->option('test-submission')) {
            $this->info('📝 Step 4: Testing Submission Creation...');
            if (!$this->testSubmissionCreation()) {
                $success = false;
            }
            $this->newLine();
        }

        // Test 5: Database Integration
        $this->info('🗄️  Step 5: Testing Database Integration...');
        if (!$this->testDatabaseIntegration()) {
            $success = false;
        }

        $this->newLine();
        if ($success) {
            $this->info('✅ All tests passed! Docuseal integration is working correctly.');
        } else {
            $this->error('❌ Some tests failed. Please check the issues above.');
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    protected function showConfiguration(): void
    {
        $config = [
            'API Key' => config('docuseal.api_key') ? 'SET (' . strlen(config('docuseal.api_key')) . ' chars)' : 'NOT SET',
            'API URL' => config('docuseal.api_url'),
            'Account Email' => config('docuseal.account_email'),
            'Timeout' => config('docuseal.timeout', 30) . ' seconds',
            'Max Retries' => config('docuseal.max_retries', 3),
            'Episode Integration' => config('docuseal.episode_integration.auto_populate_fields') ? 'ENABLED' : 'DISABLED',
            'Smart Mapping' => config('docuseal.episode_integration.enable_smart_mapping') ? 'ENABLED' : 'DISABLED',
        ];

        $this->table(['Setting', 'Value'], collect($config)->map(fn($value, $key) => [$key, $value])->toArray());

        $this->newLine();
        $this->info('Template IDs:');
        $templates = config('docuseal.templates', []);
        $this->table(['Manufacturer', 'Template ID'], collect($templates)->map(fn($id, $key) => [ucfirst(str_replace('_', ' ', $key)), $id])->toArray());
    }

    protected function testConfiguration(): bool
    {
        $apiKey = config('docuseal.api_key');
        $apiUrl = config('docuseal.api_url');

        if (!$apiKey) {
            $this->error('❌ DOCUSEAL_API_KEY not configured in .env file');
            return false;
        }

        if (!$apiUrl) {
            $this->error('❌ DOCUSEAL_API_URL not configured');
            return false;
        }

        $this->info("✅ API Key: SET ({" . strlen($apiKey) . "} characters)");
        $this->info("✅ API URL: {$apiUrl}");

        return true;
    }

    protected function testAuthentication(): bool
    {
        try {
            $result = $this->docuSealService->testConnection();

            if ($result['success']) {
                $this->info('✅ Authentication successful');
                $this->info("   Templates found: {$result['templates_count']}");
                return true;
            } else {
                $this->error('❌ Authentication failed');
                $this->error("   Error: {$result['message']}");

                if ($result['error_type'] ?? null === 'authentication') {
                    $this->warn('   💡 Suggestion: Check if your DOCUSEAL_API_KEY is correct');
                    $this->warn('   💡 Key format should be: RDiw3DDJVmRqKVYMCeqXVqmZg1zg4WfvReWNnRAmrUD');
                }

                return false;
            }
        } catch (\Exception $e) {
            $this->error('❌ Authentication test failed with exception');
            $this->error("   Exception: {$e->getMessage()}");
            return false;
        }
    }

    protected function testTemplates(): bool
    {
        try {
            $apiKey = config('docuseal.api_key');
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->timeout(30)->get('https://api.docuseal.com/templates');

            if ($response->successful()) {
                $templates = $response->json();
                $count = count($templates);
                $this->info("✅ Successfully fetched {$count} templates");

                if ($count > 0) {
                    $this->info('   Sample templates:');
                    foreach (array_slice($templates, 0, 3) as $template) {
                        $this->info("   - {$template['name']} (ID: {$template['id']})");
                    }
                }

                return true;
            } else {
                $this->error('❌ Failed to fetch templates');
                $this->error("   Status: {$response->status()}");
                $this->error("   Response: {$response->body()}");
                return false;
            }
        } catch (\Exception $e) {
            $this->error('❌ Template test failed with exception');
            $this->error("   Exception: {$e->getMessage()}");
            return false;
        }
    }

    protected function testSubmissionCreation(): bool
    {
        try {
            // Find a manufacturer to test with
            $manufacturerId = $this->option('manufacturer');

            if (!$manufacturerId) {
                $manufacturer = Manufacturer::first();
                if (!$manufacturer) {
                    $this->warn('⚠️  No manufacturers found in database. Skipping submission test.');
                    return true;
                }
                $manufacturerId = $manufacturer->id;
            }

            // Get template for manufacturer
            $template = DocusealTemplate::where('manufacturer_id', $manufacturerId)->first();

            if (!$template) {
                $this->warn("⚠️  No Docuseal template found for manufacturer {$manufacturerId}. Skipping submission test.");
                return true;
            }

            // Test submission data
            $testData = [
                'user_email' => 'test@mscwoundcare.com',
                'integration_email' => 'provider@example.com',
                'prefill_data' => [
                    'patient_first_name' => 'Test',
                    'patient_last_name' => 'Patient',
                    'patient_dob' => '1980-01-01',
                    'provider_name' => 'Dr. Test Provider',
                    'facility_name' => 'Test Clinic',
                ],
                'manufacturerId' => $manufacturerId,
                'productCode' => 'TEST001',
            ];

            // Call the controller method directly
            $controller = app(\App\Http\Controllers\QuickRequestController::class);
            $request = new \Illuminate\Http\Request();
            $request->merge($testData);

            $response = $controller->generateSubmissionSlug($request);
            $responseData = $response->getData(true);

            if ($response->getStatusCode() === 200 && $responseData['success']) {
                $this->info('✅ Submission creation successful');
                $this->info("   Slug: {$responseData['slug']}");
                $this->info("   Template: {$responseData['template_name']}");
                $this->info("   Mapped Fields: {$responseData['mapped_fields_count']}");
                return true;
            } else {
                $this->error('❌ Submission creation failed');
                $this->error("   Error: " . ($responseData['error'] ?? 'Unknown error'));
                return false;
            }

        } catch (\Exception $e) {
            $this->error('❌ Submission test failed with exception');
            $this->error("   Exception: {$e->getMessage()}");
            return false;
        }
    }

    protected function testDatabaseIntegration(): bool
    {
        try {
            $manufacturerCount = Manufacturer::count();
            $templateCount = DocusealTemplate::count();

            $this->info("✅ Database integration working");
            $this->info("   Manufacturers: {$manufacturerCount}");
            $this->info("   Docuseal Templates: {$templateCount}");

            if ($templateCount === 0) {
                $this->warn('⚠️  No Docuseal templates found in database.');
                $this->warn('   💡 Run: php artisan docuseal:sync-templates to populate templates');
            }

            return true;
        } catch (\Exception $e) {
            $this->error('❌ Database integration test failed');
            $this->error("   Exception: {$e->getMessage()}");
            return false;
        }
    }
}

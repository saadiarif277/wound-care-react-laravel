<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TestAhdsConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fhir:test-ahds-connection 
                            {--timeout=30 : Connection timeout in seconds}
                            {--details : Show detailed output}
                            {--skip-write : Skip write tests (read-only mode)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to Azure Health Data Services workspace';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Testing Azure Health Data Services Connection...');
        $this->newLine();

        $startTime = microtime(true);
        $allTestsPassed = true;

        try {
            // Test 1: Configuration Check
            $this->info('📋 Step 1: Checking Configuration...');
            if (!$this->checkConfiguration()) {
                $allTestsPassed = false;
                $this->error('❌ Configuration check failed');
                return Command::FAILURE;
            }
            $this->info('✅ Configuration check passed');
            $this->newLine();

            // Test 2: OAuth2 Authentication
            $this->info('🔐 Step 2: Testing OAuth2 Authentication...');
            $token = $this->testAuthentication();
            if (!$token) {
                $allTestsPassed = false;
                $this->error('❌ Authentication failed');
                return Command::FAILURE;
            }
            $this->info('✅ Authentication successful');
            $this->newLine();

            // Test 3: Read Access (CapabilityStatement)
            $this->info('📖 Step 3: Testing Read Access...');
            if (!$this->testReadAccess($token)) {
                $allTestsPassed = false;
                $this->error('❌ Read access test failed');
                return Command::FAILURE;
            }
            $this->info('✅ Read access test passed');
            $this->newLine();

            // Test 4: Write Access (unless skipped)
            if (!$this->option('skip-write')) {
                $this->info('✏️  Step 4: Testing Write Access...');
                if (!$this->testWriteAccess($token)) {
                    $allTestsPassed = false;
                    $this->error('❌ Write access test failed');
                    return Command::FAILURE;
                }
                $this->info('✅ Write access test passed');
                $this->newLine();
            } else {
                $this->warn('⚠️  Step 4: Write access test skipped (--skip-write flag)');
                $this->newLine();
            }

            // Test 5: Bundle Operations
            if (!$this->option('skip-write')) {
                $this->info('📦 Step 5: Testing Bundle Operations...');
                if (!$this->testBundleOperations($token)) {
                    $allTestsPassed = false;
                    $this->error('❌ Bundle operations test failed');
                    return Command::FAILURE;
                }
                $this->info('✅ Bundle operations test passed');
                $this->newLine();
            } else {
                $this->warn('⚠️  Step 5: Bundle operations test skipped (--skip-write flag)');
                $this->newLine();
            }

            // Performance Summary
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("⏱️  Total test time: {$totalTime}ms");
            $this->newLine();

            if ($allTestsPassed) {
                $this->info('🎉 All Azure Health Data Services tests passed!');
                $this->info('Your AHDS workspace is ready for use.');
                return Command::SUCCESS;
            } else {
                $this->error('❌ Some tests failed. Please check the configuration and try again.');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('💥 Unexpected error: ' . $e->getMessage());
            if ($this->option('details')) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    /**
     * Check if all required configuration is present
     */
    private function checkConfiguration(): bool
    {
        $requiredConfig = [
            'AZURE_HEALTH_DATA_SERVICES_WORKSPACE_URL',
            'AZURE_HEALTH_DATA_SERVICES_TENANT_ID',
            'AZURE_HEALTH_DATA_SERVICES_CLIENT_ID',
            'AZURE_HEALTH_DATA_SERVICES_CLIENT_SECRET',
        ];

        $missing = [];
        foreach ($requiredConfig as $config) {
            if (empty(env($config))) {
                $missing[] = $config;
            }
        }

        if (!empty($missing)) {
            $this->error('Missing required environment variables:');
            foreach ($missing as $var) {
                $this->error("  - {$var}");
            }
            return false;
        }

        // Validate workspace URL format
        $workspaceUrl = env('AZURE_HEALTH_DATA_SERVICES_WORKSPACE_URL');
        if (!filter_var($workspaceUrl, FILTER_VALIDATE_URL) || 
            !str_contains($workspaceUrl, '.fhir.azurehealthcareapis.com')) {
            $this->error('Invalid workspace URL format. Expected: https://{workspace}.fhir.azurehealthcareapis.com');
            return false;
        }

        if ($this->option('details')) {
            $this->info('Configuration details:');
            $this->info('  - Workspace URL: ' . $workspaceUrl);
            $this->info('  - Tenant ID: ' . env('AZURE_HEALTH_DATA_SERVICES_TENANT_ID'));
            $this->info('  - Client ID: ' . env('AZURE_HEALTH_DATA_SERVICES_CLIENT_ID'));
            $this->info('  - Scope: ' . env('AZURE_HEALTH_DATA_SERVICES_SCOPE', 'https://azurehealthcareapis.com/.default'));
        }

        return true;
    }

    /**
     * Test OAuth2 authentication
     */
    private function testAuthentication(): ?string
    {
        $startTime = microtime(true);
        
        $tenantId = env('AZURE_HEALTH_DATA_SERVICES_TENANT_ID');
        $clientId = env('AZURE_HEALTH_DATA_SERVICES_CLIENT_ID');
        $clientSecret = env('AZURE_HEALTH_DATA_SERVICES_CLIENT_SECRET');
        $scope = env('AZURE_HEALTH_DATA_SERVICES_SCOPE', 'https://azurehealthcareapis.com/.default');
        $oauthEndpoint = env('AZURE_HEALTH_DATA_SERVICES_OAUTH_ENDPOINT', 'https://login.microsoftonline.com');

        $tokenUrl = "{$oauthEndpoint}/{$tenantId}/oauth2/v2.0/token";

        try {
            $response = Http::timeout($this->option('timeout'))
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => $scope,
                ]);

            $authTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $tokenData = $response->json();
                $token = $tokenData['access_token'];
                
                if ($this->option('details')) {
                    $this->info("  - Authentication time: {$authTime}ms");
                    $this->info('  - Token type: ' . ($tokenData['token_type'] ?? 'Bearer'));
                    $this->info('  - Expires in: ' . ($tokenData['expires_in'] ?? 'unknown') . ' seconds');
                }

                return $token;
            } else {
                $this->error('Authentication failed with status: ' . $response->status());
                if ($this->option('details')) {
                    $this->error('Response: ' . $response->body());
                }
                return null;
            }
        } catch (\Exception $e) {
            $this->error('Authentication request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Test read access by fetching CapabilityStatement
     */
    private function testReadAccess(string $token): bool
    {
        $startTime = microtime(true);
        $workspaceUrl = env('AZURE_HEALTH_DATA_SERVICES_WORKSPACE_URL');

        try {
            $response = Http::timeout($this->option('timeout'))
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/fhir+json',
                ])
                ->get("{$workspaceUrl}/metadata");

            $readTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $capability = $response->json();
                
                if ($this->option('details')) {
                    $this->info("  - Read operation time: {$readTime}ms");
                    $this->info('  - FHIR version: ' . ($capability['fhirVersion'] ?? 'unknown'));
                    $this->info('  - Server software: ' . ($capability['software']['name'] ?? 'unknown'));
                    $this->info('  - Implementation description: ' . ($capability['implementation']['description'] ?? 'unknown'));
                }

                return true;
            } else {
                $this->error('Read access failed with status: ' . $response->status());
                if ($this->option('details')) {
                    $this->error('Response: ' . $response->body());
                }
                return false;
            }
        } catch (\Exception $e) {
            $this->error('Read access request failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test write access by creating and deleting a test patient
     */
    private function testWriteAccess(string $token): bool
    {
        $workspaceUrl = env('AZURE_HEALTH_DATA_SERVICES_WORKSPACE_URL');
        $testPatientId = 'test-patient-' . uniqid();

        // Create test patient
        $testPatient = [
            'resourceType' => 'Patient',
            'id' => $testPatientId,
            'identifier' => [
                [
                    'system' => 'http://mscwoundcare.com/test-patients',
                    'value' => $testPatientId
                ]
            ],
            'name' => [
                [
                    'use' => 'official',
                    'family' => 'TestPatient',
                    'given' => ['AHDS', 'Connection']
                ]
            ],
            'gender' => 'unknown',
            'birthDate' => '1990-01-01'
        ];

        try {
            // Create patient
            $createStart = microtime(true);
            $createResponse = Http::timeout($this->option('timeout'))
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/fhir+json',
                    'Accept' => 'application/fhir+json',
                ])
                ->put("{$workspaceUrl}/Patient/{$testPatientId}", $testPatient);

            $createTime = round((microtime(true) - $createStart) * 1000, 2);

            if (!$createResponse->successful()) {
                $this->error('Patient creation failed with status: ' . $createResponse->status());
                if ($this->option('details')) {
                    $this->error('Response: ' . $createResponse->body());
                }
                return false;
            }

            // Delete patient (cleanup)
            $deleteStart = microtime(true);
            $deleteResponse = Http::timeout($this->option('timeout'))
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                ])
                ->delete("{$workspaceUrl}/Patient/{$testPatientId}");

            $deleteTime = round((microtime(true) - $deleteStart) * 1000, 2);

            if ($this->option('details')) {
                $this->info("  - Patient creation time: {$createTime}ms");
                $this->info("  - Patient deletion time: {$deleteTime}ms");
                $this->info('  - Created patient ID: ' . $testPatientId);
            }

            return $deleteResponse->successful() || $deleteResponse->status() === 404;

        } catch (\Exception $e) {
            $this->error('Write access test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test bundle operations (transaction support)
     */
    private function testBundleOperations(string $token): bool
    {
        $workspaceUrl = env('AZURE_HEALTH_DATA_SERVICES_WORKSPACE_URL');
        $testBundleId = 'test-bundle-' . uniqid();

        // Create test bundle with multiple resources
        $testBundle = [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'entry' => [
                [
                    'request' => [
                        'method' => 'PUT',
                        'url' => "Patient/test-patient-{$testBundleId}"
                    ],
                    'resource' => [
                        'resourceType' => 'Patient',
                        'id' => "test-patient-{$testBundleId}",
                        'name' => [
                            [
                                'family' => 'BundleTest',
                                'given' => ['AHDS']
                            ]
                        ],
                        'gender' => 'unknown'
                    ]
                ],
                [
                    'request' => [
                        'method' => 'DELETE',
                        'url' => "Patient/test-patient-{$testBundleId}"
                    ]
                ]
            ]
        ];

        try {
            $bundleStart = microtime(true);
            $bundleResponse = Http::timeout($this->option('timeout'))
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/fhir+json',
                    'Accept' => 'application/fhir+json',
                ])
                ->post("{$workspaceUrl}/", $testBundle);

            $bundleTime = round((microtime(true) - $bundleStart) * 1000, 2);

            if ($bundleResponse->successful()) {
                $bundleResult = $bundleResponse->json();
                
                if ($this->option('details')) {
                    $this->info("  - Bundle operation time: {$bundleTime}ms");
                    $this->info('  - Bundle type: ' . ($bundleResult['type'] ?? 'unknown'));
                    $this->info('  - Entry count: ' . count($bundleResult['entry'] ?? []));
                }

                return true;
            } else {
                $this->error('Bundle operation failed with status: ' . $bundleResponse->status());
                if ($this->option('details')) {
                    $this->error('Response: ' . $bundleResponse->body());
                }
                return false;
            }
        } catch (\Exception $e) {
            $this->error('Bundle operation test failed: ' . $e->getMessage());
            return false;
        }
    }
}

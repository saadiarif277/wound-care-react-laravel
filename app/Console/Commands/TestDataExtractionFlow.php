<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataExtractionService;
use App\Services\UnifiedFieldMappingService;
use App\Models\User;
use App\Models\Fhir\Facility;
use Illuminate\Support\Facades\Auth;

class TestDataExtractionFlow extends Command
{
    protected $signature = 'test:acz-data-flow 
                            {--provider= : Provider ID to test}
                            {--facility= : Facility ID to test}
                            {--debug : Show detailed debug output}';
    
    protected $description = 'Test ACZ & Associates data extraction and field mapping flow';

    public function handle(
        DataExtractionService $dataExtractionService,
        UnifiedFieldMappingService $fieldMappingService
    ) {
        $this->info('🧪 Testing ACZ & Associates Data Extraction Flow');
        $this->newLine();
        
        // Get test parameters
        $providerId = $this->option('provider') ?? $this->askForProvider();
        $facilityId = $this->option('facility') ?? $this->askForFacility($providerId);
        $debug = $this->option('debug');
        
        // Set up test context
        $provider = User::find($providerId);
        $facility = Facility::withoutGlobalScopes()->find($facilityId);
        
        if (!$provider) {
            $this->error("❌ Provider {$providerId} not found");
            return Command::FAILURE;
        }
        
        if (!$facility) {
            $this->error("❌ Facility {$facilityId} not found");
            
            // Show available facilities for testing
            $availableFacilities = Facility::withoutGlobalScopes()->select('id', 'name', 'organization_id')->get();
            if ($availableFacilities->count() > 0) {
                $this->info("Available facilities:");
                foreach ($availableFacilities as $f) {
                    $this->info("  ID: {$f->id} - {$f->name} (Org: {$f->organization_id})");
                }
            } else {
                $this->error("No facilities found in database. Run: php artisan db:seed --class=QuickFixFacilitySeeder");
            }
            
            return Command::FAILURE;
        }
        
        // Authenticate as the provider for permission checks
        Auth::login($provider);
        
        $this->info("👤 Testing as: {$provider->first_name} {$provider->last_name} (ID: {$provider->id})");
        $this->info("🏥 Testing facility: {$facility->name} (ID: {$facility->id})");
        $this->newLine();
        
        // Test 1: Basic Data Extraction
        $this->info('📊 Test 1: Data Extraction');
        $this->testDataExtraction($dataExtractionService, $providerId, $facilityId, $debug);
        
        // Test 2: Field Mapping
        $this->info('🔄 Test 2: Field Mapping');
        $this->testFieldMapping($fieldMappingService, $providerId, $facilityId, $debug);
        
        // Test 3: Key Field Validation
        $this->info('✅ Test 3: Key Field Validation');
        $this->validateKeyFields($dataExtractionService, $fieldMappingService, $providerId, $facilityId);
        
        $this->newLine();
        $this->info('🎉 Testing completed!');
        
        return Command::SUCCESS;
    }
    
    private function askForProvider(): int
    {
        $providers = User::whereHas('roles', fn($q) => $q->where('slug', 'provider'))
            ->select('id', 'first_name', 'last_name', 'email')
            ->limit(10)
            ->get();
            
        if ($providers->isEmpty()) {
            $this->error('No providers found');
            exit(1);
        }
        
        $this->table(['ID', 'Name', 'Email'], $providers->map(fn($p) => [
            $p->id,
            $p->first_name . ' ' . $p->last_name,
            $p->email
        ])->toArray());
        
        return $this->ask('Select provider ID');
    }
    
    private function askForFacility(int $providerId): int
    {
        $provider = User::find($providerId);
        $facilities = $provider->facilities()
            ->select('facilities.id', 'facilities.name', 'facilities.address')
            ->limit(10)
            ->get();
            
        if ($facilities->isEmpty()) {
            $this->error("Provider {$providerId} has no associated facilities");
            exit(1);
        }
        
        $this->table(['ID', 'Name', 'Address'], $facilities->map(fn($f) => [
            $f->id,
            $f->name,
            $f->address
        ])->toArray());
        
        return $this->ask('Select facility ID');
    }
    
    private function testDataExtraction(DataExtractionService $service, int $providerId, int $facilityId, bool $debug)
    {
        $context = [
            'provider_id' => $providerId,
            'facility_id' => $facilityId,
            'manufacturer_id' => 1, // ACZ & Associates
            
            // Sample form data
            'place_of_service' => '11',
            'primary_insurance_name' => 'Medicare A & B',
            'primary_member_id' => '123456789A',
            'primary_payer_phone' => '(800) 633-4227',
            'secondary_insurance_name' => '6 Degrees Health, Inc.',
            'secondary_member_id' => '222332804',
            'has_secondary_insurance' => true,
            'prior_auth_permission' => true,
            'hospice_status' => false,
            'part_a_status' => false,
            'global_period_status' => true,
            'selected_products' => [
                ['product' => ['code' => 'Q4205']],
                ['product' => ['code' => 'Q4290']],
            ],
        ];
        
        if ($debug) {
            $this->info("🔍 Context being passed to extractData:");
            foreach ($context as $key => $value) {
                if (is_array($value)) {
                    $this->info("  {$key}: " . json_encode($value));
                } else {
                    $this->info("  {$key}: {$value}");
                }
            }
            $this->newLine();
        }

        try {
            $extractedData = $service->extractData($context);
            
            if ($debug) {
                $this->info("🔍 Checking if facility data was extracted...");
                $facilityFields = array_filter($extractedData, fn($key) => str_starts_with($key, 'facility_'), ARRAY_FILTER_USE_KEY);
                $this->info("  Facility fields found: " . count($facilityFields));
                if (!empty($facilityFields)) {
                    foreach ($facilityFields as $key => $value) {
                        $this->info("    {$key}: {$value}");
                    }
                }
                $this->newLine();
            }
            
            $keyChecks = [
                'provider_name' => $extractedData['provider_name'] ?? null,
                'provider_npi' => $extractedData['provider_npi'] ?? null,
                'facility_name' => $extractedData['facility_name'] ?? null,
                'facility_npi' => $extractedData['facility_npi'] ?? null,
                'patient_insurance' => $extractedData['primary_insurance_name'] ?? null,
                'facility_address' => $extractedData['facility_address'] ?? null,
            ];
            
            foreach ($keyChecks as $field => $value) {
                $status = $value ? '✅' : '❌';
                $displayValue = $value ?: '<missing>';
                $this->line("  {$status} {$field}: {$displayValue}");
            }
            
            if ($debug) {
                $this->newLine();
                $this->line('📋 All extracted fields:');
                $this->table(['Field', 'Value'], 
                    collect($extractedData)->map(fn($v, $k) => [$k, $v])->take(15)->toArray()
                );
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Data extraction failed: " . $e->getMessage());
        }
    }
    
    private function testFieldMapping(UnifiedFieldMappingService $service, int $providerId, int $facilityId, bool $debug)
    {
        $context = [
            'provider_id' => $providerId,
            'facility_id' => $facilityId,
            'manufacturer_id' => 1,
            'primary_insurance_name' => 'Medicare A & B',
            'primary_member_id' => '123456789A',
            'secondary_insurance_name' => '6 Degrees Health, Inc.',
            'secondary_member_id' => '222332804',
            'place_of_service' => '11',
            'hospice_status' => false,
            'global_period_status' => true,
        ];
        
        try {
            $result = $service->mapEpisodeToTemplate(null, 'ACZ & ASSOCIATES', $context, 'IVR');
            
            $keyMappedFields = [
                'physician_name' => $result['data']['physician_name'] ?? null,
                'facility_name' => $result['data']['facility_name'] ?? null,
                'primary_policy_number' => $result['data']['primary_policy_number'] ?? null,
                'secondary_policy_number' => $result['data']['secondary_policy_number'] ?? null,
                'pos_11' => $result['data']['pos_11'] ?? null,
                'patient_in_hospice_no' => $result['data']['patient_in_hospice_no'] ?? null,
                'patient_post_op_global_yes' => $result['data']['patient_post_op_global_yes'] ?? null,
            ];
            
            foreach ($keyMappedFields as $field => $value) {
                $status = $value !== null ? '✅' : '❌';
                $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : ($value ?: '<missing>');
                $this->line("  {$status} {$field}: {$displayValue}");
            }
            
            if ($debug) {
                $this->newLine();
                $this->line('📋 All mapped fields:');
                $this->table(['Field', 'Value'], 
                    collect($result['data'])->map(fn($v, $k) => [
                        $k, 
                        is_bool($v) ? ($v ? 'true' : 'false') : $v
                    ])->take(20)->toArray()
                );
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Field mapping failed: " . $e->getMessage());
        }
    }
    
    private function validateKeyFields(DataExtractionService $dataService, UnifiedFieldMappingService $mappingService, int $providerId, int $facilityId)
    {
        $this->info('🔍 Validating critical fields for ACZ & ASSOCIATES IVR...');
        
        $context = [
            'provider_id' => $providerId,
            'facility_id' => $facilityId,
            'manufacturer_id' => 1,
            'primary_insurance_name' => 'Medicare A & B',
            'primary_member_id' => '123456789A',
            'secondary_insurance_name' => '6 Degrees Health, Inc.',
            'secondary_member_id' => '222332804',
            'place_of_service' => '11',
            'hospice_status' => false,
            'global_period_status' => true,
        ];
        
        try {
                         $mappingResult = $mappingService->mapEpisodeToTemplate(null, 'ACZ & ASSOCIATES', $context, 'IVR');
            $mappedData = $mappingResult['data'];
            
            $criticalFields = [
                'Patient Address' => [
                    'patient_address' => $mappedData['patient_address'] ?? null,
                    'patient_city_state_zip' => $mappedData['patient_city_state_zip'] ?? null,
                ],
                'Insurance Info' => [
                    'primary_policy_number' => $mappedData['primary_policy_number'] ?? null,
                    'secondary_policy_number' => $mappedData['secondary_policy_number'] ?? null,
                ],
                'Facility Info' => [
                    'facility_name' => $mappedData['facility_name'] ?? null,
                    'facility_npi' => $mappedData['facility_npi'] ?? null,
                    'facility_city_state_zip' => $mappedData['facility_city_state_zip'] ?? null,
                ],
                'Checkboxes' => [
                    'pos_11' => $mappedData['pos_11'] ?? null,
                    'patient_in_hospice_no' => $mappedData['patient_in_hospice_no'] ?? null,
                    'patient_post_op_global_yes' => $mappedData['patient_post_op_global_yes'] ?? null,
                ],
            ];
            
            $allPassing = true;
            
            foreach ($criticalFields as $category => $fields) {
                $this->line("  📂 {$category}:");
                foreach ($fields as $fieldName => $fieldValue) {
                    $status = $fieldValue !== null ? '✅' : '❌';
                    if ($fieldValue === null) $allPassing = false;
                    
                    $displayValue = is_bool($fieldValue) ? ($fieldValue ? 'true' : 'false') : ($fieldValue ?: '<missing>');
                    $this->line("    {$status} {$fieldName}: {$displayValue}");
                }
            }
            
            $this->newLine();
            if ($allPassing) {
                $this->info('🎉 All critical fields are properly mapped!');
            } else {
                $this->warn('⚠️ Some critical fields are missing - please review the mappings');
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Field validation failed: " . $e->getMessage());
        }
    }
} 
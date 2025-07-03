<?php

namespace Tests\Integration;

use App\Services\UnifiedFieldMappingService;
use App\Services\DocusealService;
use App\Services\FieldMapping\DataExtractor;
use App\Services\FieldMapping\FieldTransformer;
use App\Services\FieldMapping\FieldMatcher;
use App\Services\FhirService;
use App\Models\Episode;
use App\Models\Patient;
use App\Models\ProductRequest;
use App\Models\Provider;
use App\Models\Facility;
use App\Models\Product;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class FieldMappingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private UnifiedFieldMappingService $fieldMappingService;
    private DocusealService $docuSealService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache for clean tests
        Cache::flush();
        
        // Create services with real dependencies
        $fhirService = $this->createMock(FhirService::class);
        $dataExtractor = new DataExtractor($fhirService);
        $fieldTransformer = new FieldTransformer();
        $fieldMatcher = new FieldMatcher();
        
        $this->fieldMappingService = new UnifiedFieldMappingService(
            $dataExtractor,
            $fieldTransformer,
            $fieldMatcher
        );
        
        $this->docuSealService = new DocusealService($this->fieldMappingService);

        // Mock FHIR responses
        $fhirService->method('getPatient')->willReturn([
            'id' => 'fhir-patient-123',
            'name' => [['given' => ['John'], 'family' => 'Doe']],
            'birthDate' => '1980-01-01',
            'telecom' => [
                ['system' => 'phone', 'value' => '555-123-4567'],
                ['system' => 'email', 'value' => 'john.doe@example.com']
            ]
        ]);

        // Mock Docuseal API
        Http::fake([
            'api.docuseal.co/*' => Http::response([
                'id' => 'submission-123',
                'status' => 'pending',
                'template_id' => 'template-123'
            ], 201)
        ]);
    }

    /** @test */
    public function it_performs_complete_episode_to_docuseal_workflow()
    {
        // Create complete test data scenario
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1980-01-01',
            'phone' => '5551234567',
            'email' => 'john.doe@example.com',
            'address_line1' => '123 Main St',
            'city' => 'Springfield',
            'state' => 'IL',
            'zip_code' => '62701'
        ]);

        $provider = Provider::factory()->create([
            'first_name' => 'Dr. Jane',
            'last_name' => 'Smith',
            'npi' => '1234567890',
            'email' => 'dr.smith@example.com'
        ]);

        $facility = Facility::factory()->create([
            'name' => 'Springfield Medical Center'
        ]);

        $product = Product::factory()->create([
            'name' => 'Membrane Wrap',
            'manufacturer' => 'ACZ'
        ]);

        $episode = Episode::factory()->create([
            'patient_id' => $patient->id,
            'episode_number' => 'EP-2023-001'
        ]);

        $productRequest = ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'provider_id' => $provider->id,
            'facility_id' => $facility->id,
            'product_id' => $product->id,
            'status' => 'approved',
            'wound_type' => 'diabetic_ulcer',
            'wound_location' => 'left_foot',
            'wound_length' => 5.2,
            'wound_width' => 3.1,
            'wound_depth' => 0.8,
            'wound_start_date' => '2023-01-01',
            'primary_diagnosis_code' => 'E11.621',
            'primary_insurance_name' => 'Medicare',
            'primary_member_id' => 'MEDICARE123456'
        ]);

        // Step 1: Test field mapping
        $mappingResult = $this->fieldMappingService->mapEpisodeToTemplate(
            $episode->id,
            'ACZ'
        );

        $this->assertIsArray($mappingResult);
        $this->assertArrayHasKey('data', $mappingResult);
        $this->assertArrayHasKey('validation', $mappingResult);
        $this->assertArrayHasKey('completeness', $mappingResult);

        // Verify mapped data contains expected fields
        $data = $mappingResult['data'];
        $this->assertEquals('John', $data['patient_first_name']);
        $this->assertEquals('Doe', $data['patient_last_name']);
        $this->assertEquals('01/01/1980', $data['patient_dob']); // Should be transformed to M/d/Y
        $this->assertEquals('(555) 123-4567', $data['patient_phone']); // Should be formatted
        $this->assertEquals('diabetic_ulcer', $data['wound_type']);
        $this->assertEquals('1234567890', $data['provider_npi']);

        // Verify computed fields
        $this->assertEquals(16.12, $data['wound_size_total'], '', 0.01); // 5.2 * 3.1
        $this->assertGreaterThan(0, $data['wound_duration_days']);

        // Verify validation
        $this->assertTrue($mappingResult['validation']['valid']);
        $this->assertEmpty($mappingResult['validation']['errors']);

        // Verify completeness calculation
        $this->assertGreaterThan(0, $mappingResult['completeness']['percentage']);
        $this->assertIsArray($mappingResult['completeness']['field_status']);

        // Step 2: Test Docuseal submission creation
        $submissionResult = $this->docuSealService->createOrUpdateSubmission(
            $episode->id,
            'ACZ'
        );

        $this->assertTrue($submissionResult['success']);
        $this->assertArrayHasKey('submission', $submissionResult);
        $this->assertArrayHasKey('ivr_episode', $submissionResult);
        $this->assertEquals('submission-123', $submissionResult['submission']['id']);

        // Verify IVR episode was created in database
        $this->assertDatabaseHas('patient_manufacturer_ivr_episodes', [
            'episode_id' => $episode->id,
            'manufacturer_name' => 'ACZ',
            'docuseal_submission_id' => 'submission-123',
            'docuseal_status' => 'pending'
        ]);
    }

    /** @test */
    public function it_handles_field_transformations_correctly()
    {
        $patient = Patient::factory()->create([
            'date_of_birth' => '1980-12-25',
            'phone' => '15551234567' // With country code
        ]);

        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved',
            'wound_start_date' => now()->subWeeks(6)->format('Y-m-d')
        ]);

        $result = $this->fieldMappingService->mapEpisodeToTemplate($episode->id, 'ACZ');

        $data = $result['data'];

        // Test date transformation
        $this->assertEquals('12/25/1980', $data['patient_dob']);

        // Test phone transformation
        $this->assertEquals('+1 (555) 123-4567', $data['patient_phone']);

        // Test computed wound duration
        $this->assertGreaterThan(35, $data['wound_duration_days']); // ~6 weeks
        $this->assertEquals(6, $data['wound_duration_weeks']);
    }

    /** @test */
    public function it_applies_business_rules_correctly()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        // Create wound with short duration (should trigger ACZ warning)
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved',
            'wound_start_date' => now()->subWeeks(2)->format('Y-m-d') // Only 2 weeks
        ]);

        $result = $this->fieldMappingService->mapEpisodeToTemplate($episode->id, 'ACZ');

        // Should have validation warning for ACZ wound duration requirement
        $this->assertNotEmpty($result['validation']['warnings']);
        $warnings = $result['validation']['warnings'];
        $this->assertContains('Wound duration does not meet ACZ requirement of > 4 weeks', $warnings);
    }

    /** @test */
    public function it_handles_fuzzy_field_matching()
    {
        // Test with non-standard field names that should be matched via fuzzy matching
        $patient = Patient::factory()->create([
            'first_name' => 'John', // Should match patient_first_name
            'last_name' => 'Doe'
        ]);

        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        $result = $this->fieldMappingService->mapEpisodeToTemplate($episode->id, 'ACZ');

        // Should successfully map fields despite non-exact field names
        $this->assertEquals('John', $result['data']['patient_first_name']);
        $this->assertEquals('Doe', $result['data']['patient_last_name']);
    }

    /** @test */
    public function it_handles_missing_optional_fields_gracefully()
    {
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => null, // Missing optional field
            'email' => null
        ]);

        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        $result = $this->fieldMappingService->mapEpisodeToTemplate($episode->id, 'ACZ');

        // Should still be valid even with missing optional fields
        $this->assertTrue($result['validation']['valid']);
        $this->assertLessThan(100, $result['completeness']['percentage']); // But completeness should be less than 100%
        
        // Required fields should still be present
        $this->assertEquals('John', $result['data']['patient_first_name']);
        $this->assertEquals('Doe', $result['data']['patient_last_name']);
    }

    /** @test */
    public function it_caches_extracted_data_properly()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        // First call should populate cache
        $startTime = microtime(true);
        $result1 = $this->fieldMappingService->mapEpisodeToTemplate($episode->id, 'ACZ');
        $duration1 = microtime(true) - $startTime;

        // Second call should use cache and be faster
        $startTime = microtime(true);
        $result2 = $this->fieldMappingService->mapEpisodeToTemplate($episode->id, 'ACZ');
        $duration2 = microtime(true) - $startTime;

        // Results should be identical
        $this->assertEquals($result1['data'], $result2['data']);
        
        // Second call should be significantly faster (cache hit)
        $this->assertLessThan($duration1, $duration2);
    }

    /** @test */
    public function it_logs_mapping_analytics_correctly()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        $result = $this->fieldMappingService->mapEpisodeToTemplate($episode->id, 'ACZ');

        // Should have logged the mapping operation
        $this->assertDatabaseHas('field_mapping_logs', [
            'episode_id' => $episode->id,
            'manufacturer_name' => 'ACZ',
            'mapping_type' => 'docuseal',
            'source_service' => 'UnifiedFieldMappingService'
        ]);
    }

    /** @test */
    public function it_handles_webhook_processing_end_to_end()
    {
        // Create IVR episode with Docuseal submission
        $ivrEpisode = \App\Models\PatientManufacturerIVREpisode::factory()->create([
            'docuseal_submission_id' => 'webhook-submission-123',
            'docuseal_status' => 'pending'
        ]);

        $webhookPayload = [
            'event_type' => 'submission.completed',
            'data' => [
                'id' => 'webhook-submission-123',
                'status' => 'completed',
                'documents' => [
                    ['url' => 'https://example.com/completed-document.pdf']
                ]
            ]
        ];

        $result = $this->docuSealService->processWebhook($webhookPayload);

        $this->assertEquals('processed', $result['status']);
        $this->assertEquals('submission.completed', $result['event']);

        // Verify database was updated
        $ivrEpisode->refresh();
        $this->assertEquals('completed', $ivrEpisode->docuseal_status);
        $this->assertNotNull($ivrEpisode->completed_at);
        $this->assertEquals('https://example.com/completed-document.pdf', $ivrEpisode->signed_document_url);
    }

    /** @test */
    public function it_generates_accurate_analytics()
    {
        // Create test episodes with different statuses and manufacturers
        \App\Models\PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_name' => 'ACZ',
            'docuseal_status' => 'completed',
            'field_mapping_completeness' => 85.5,
            'required_fields_completeness' => 90.0,
            'created_at' => now()->subMinutes(60),
            'completed_at' => now()->subMinutes(30)
        ]);

        \App\Models\PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_name' => 'ACZ',
            'docuseal_status' => 'pending',
            'field_mapping_completeness' => 75.0,
            'required_fields_completeness' => 80.0
        ]);

        \App\Models\PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_name' => 'Advanced Health',
            'docuseal_status' => 'completed',
            'field_mapping_completeness' => 95.0
        ]);

        $analytics = $this->docuSealService->generateAnalytics('ACZ');

        $this->assertEquals(2, $analytics['total_submissions']);
        $this->assertEquals(1, $analytics['status_breakdown']['completed']);
        $this->assertEquals(1, $analytics['status_breakdown']['pending']);
        $this->assertEquals(50.0, $analytics['completion_rate']);
        $this->assertEquals(80.25, $analytics['average_field_completeness']); // (85.5 + 75) / 2
        $this->assertEquals(85.0, $analytics['average_required_field_completeness']); // (90 + 80) / 2
        $this->assertEquals(30, $analytics['average_time_to_complete_minutes']);
    }
}
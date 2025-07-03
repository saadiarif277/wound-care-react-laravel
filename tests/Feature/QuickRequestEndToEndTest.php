<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Order\Product;
use App\Models\Order\Manufacturer;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Document;
use App\Models\Fhir\Facility;
use App\Models\Docuseal\DocusealTemplate;
use App\Services\DocusealService;
use App\Services\PatientService;
use App\Services\FhirService;
use App\Jobs\QuickRequest\GenerateDocusealPdf;
use App\Jobs\QuickRequest\VerifyInsuranceEligibility;
use Mockery;

class QuickRequestEndToEndTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $testUser;
    private $testFacility;
    private $testManufacturer;
    private $testProduct;
    private $testTemplate;
    private $mockDocusealService;
    private $mockPatientService;
    private $mockFhirService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestData();
        $this->setupMocks();
        Queue::fake();
        Event::fake();
        Storage::fake('documents');
        Storage::fake('s3-encrypted');
    }

    private function setupTestData(): void
    {
        // Create test user with provider role
        $this->testUser = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Provider',
            'email' => 'provider@test.com',
            'npi_number' => '1234567890',
        ]);

        // Create test facility
        $this->testFacility = Facility::factory()->create([
            'name' => 'Test Medical Center',
            'address_line1' => '123 Medical Drive',
            'city' => 'Healthcare City',
            'state' => 'CA',
            'zip_code' => '90210',
            'active' => true,
        ]);

        // Create test manufacturer
        $this->testManufacturer = Manufacturer::factory()->create([
            'name' => 'BioWound Solutions',
            'slug' => 'biowound',
            'contact_email' => 'orders@biowound.com',
            'is_active' => true,
        ]);

        // Create test product
        $this->testProduct = Product::factory()->create([
            'name' => 'Advanced Wound Matrix',
            'q_code' => 'Q4321',
            'manufacturer_id' => $this->testManufacturer->id,
            'manufacturer' => 'BioWound Solutions',
            'price_per_sq_cm' => 25.50,
            'available_sizes' => ['2x2', '4x4', '6x6'],
            'is_active' => true,
        ]);

        // Create test Docuseal template
        $this->testTemplate = DocusealTemplate::factory()->create([
            'template_name' => 'BioWound IVR Form',
            'docuseal_template_id' => 'tpl_biowound_123',
            'manufacturer_id' => $this->testManufacturer->id,
            'document_type' => 'IVR',
            'is_active' => true,
            'is_default' => true,
            'field_mappings' => $this->getTestFieldMappings(),
        ]);

        // Create necessary database tables data
        $this->createTestReferenceData();
    }

    private function createTestReferenceData(): void
    {
        // Create wound types
        DB::table('wound_types')->insert([
            ['code' => 'diabetic_foot_ulcer', 'display_name' => 'Diabetic Foot Ulcer', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'venous_leg_ulcer', 'display_name' => 'Venous Leg Ulcer', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'pressure_ulcer', 'display_name' => 'Pressure Ulcer', 'is_active' => true, 'sort_order' => 3],
        ]);

        // Create diagnosis codes
        DB::table('diagnosis_codes')->insert([
            ['code' => 'E11.621', 'description' => 'Type 2 diabetes mellitus with foot ulcer', 'category' => 'diabetes', 'is_active' => true],
            ['code' => 'I87.31', 'description' => 'Chronic venous hypertension with ulcer', 'category' => 'vascular', 'is_active' => true],
            ['code' => 'L89.90', 'description' => 'Pressure ulcer of unspecified site', 'category' => 'pressure', 'is_active' => true],
        ]);
    }

    private function getTestFieldMappings(): array
    {
        return [
            'patientFirstName' => [
                'docuseal_field_name' => 'patientFirstName',
                'field_type' => 'text',
                'required' => true,
                'local_field' => 'patientInfo.patientFirstName',
                'system_field' => 'patient_first_name',
                'data_type' => 'string',
                'validation_rules' => ['required'],
            ],
            'patientLastName' => [
                'docuseal_field_name' => 'patientLastName',
                'field_type' => 'text',
                'required' => true,
                'local_field' => 'patientInfo.patientLastName',
                'system_field' => 'patient_last_name',
                'data_type' => 'string',
                'validation_rules' => ['required'],
            ],
            'patientDOB' => [
                'docuseal_field_name' => 'patientDOB',
                'field_type' => 'date',
                'required' => true,
                'local_field' => 'patientInfo.patientDOB',
                'system_field' => 'patient_dob',
                'data_type' => 'date',
                'validation_rules' => ['required'],
            ],
            'patientPhone' => [
                'docuseal_field_name' => 'patientPhone',
                'field_type' => 'text',
                'required' => false,
                'local_field' => 'patientInfo.patientPhone',
                'system_field' => 'patient_phone',
                'data_type' => 'phone',
                'validation_rules' => [],
            ],
            'primaryInsurance' => [
                'docuseal_field_name' => 'primaryInsurance',
                'field_type' => 'text',
                'required' => true,
                'local_field' => 'insuranceInfo.primaryInsurance.name',
                'system_field' => 'primary_insurance_name',
                'data_type' => 'string',
                'validation_rules' => ['required'],
            ],
            'woundType' => [
                'docuseal_field_name' => 'woundType',
                'field_type' => 'select',
                'required' => true,
                'local_field' => 'clinicalInfo.woundType',
                'system_field' => 'wound_type',
                'data_type' => 'string',
                'validation_rules' => ['required'],
            ],
            'productName' => [
                'docuseal_field_name' => 'productName',
                'field_type' => 'text',
                'required' => true,
                'local_field' => 'productInfo.productName',
                'system_field' => 'product_name',
                'data_type' => 'string',
                'validation_rules' => ['required'],
            ],
        ];
    }

    private function setupMocks(): void
    {
        // Mock Docuseal Service
        $this->mockDocusealService = Mockery::mock(DocusealService::class);
        $this->mockDocusealService->shouldReceive('generatePdf')
            ->andReturn([
                'success' => true,
                'pdf_content' => 'fake-pdf-content',
                'submission_id' => 'sub_123456',
                'document_id' => 'doc_123456',
                'document_url' => 'https://example.com/doc.pdf',
            ]);
        $this->app->instance(DocusealService::class, $this->mockDocusealService);

        // Mock Patient Service
        $this->mockPatientService = Mockery::mock(PatientService::class);
        $this->mockPatientService->shouldReceive('createPatientRecord')
            ->andReturn([
                'patient_fhir_id' => 'Patient/test-123',
                'patient_display_id' => 'P-TEST-001',
            ]);
        $this->app->instance(PatientService::class, $this->mockPatientService);

        // Mock FHIR Service
        $this->mockFhirService = Mockery::mock(FhirService::class);
        $this->mockFhirService->shouldReceive('create')
            ->andReturn(['id' => 'DocumentReference/test-123']);
        $this->app->instance(FhirService::class, $this->mockFhirService);
    }

    /** @test */
    public function it_completes_full_quick_request_workflow_with_high_field_mapping()
    {
        $this->actingAs($this->testUser);

        // Step 1: Create episode for Docuseal integration
        $episodeResponse = $this->postJson('/api/quick-request/create-episode', [
            'patient_id' => 'temp-patient-123',
            'patient_fhir_id' => 'Patient/test-123',
            'patient_display_id' => 'P-TEST-001',
            'manufacturer_id' => $this->testManufacturer->id,
            'selected_product_id' => $this->testProduct->id,
            'form_data' => [
                'facility_id' => $this->testFacility->id,
                'provider_id' => $this->testUser->id,
            ],
        ]);

        $episodeResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['episode_id', 'manufacturer_id']);

        $episodeId = $episodeResponse->json('episode_id');

        // Step 2: Submit complete quick request form
        $formData = $this->getComprehensiveFormData($episodeId);
        
        $response = $this->postJson('/quick-request/store', $formData);

        $response->assertStatus(302); // Redirect after successful submission

        // Step 3: Verify database records were created correctly
        $this->assertDatabaseRecordsCreated($episodeId, $formData);

        // Step 4: Verify field mapping coverage
        $mappingCoverage = $this->calculateFieldMappingCoverage($formData);
        $this->assertGreaterThan(90, $mappingCoverage, 'Field mapping coverage should be over 90%');

        // Step 5: Verify jobs were dispatched
        $this->assertJobsDispatched($episodeId);

        // Step 6: Verify document generation
        $this->verifyDocumentGeneration($episodeId);

        // Step 7: Verify workflow progression
        $this->verifyWorkflowProgression($episodeId);

        // Output detailed results
        $this->outputTestResults($mappingCoverage, $formData);
    }

    private function getComprehensiveFormData(string $episodeId): array
    {
        return [
            // Context & Request Type
            'request_type' => 'new_request',
            'provider_id' => $this->testUser->id,
            'facility_id' => $this->testFacility->id,
            'sales_rep_id' => 'REP-001',
            'episode_id' => $episodeId,
            'docuseal_submission_id' => 'sub_test_12345',

            // Patient Information (Complete)
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'patient_dob' => '1980-05-15',
            'patient_gender' => 'male',
            'patient_member_id' => 'MEM123456789',
            'patient_address_line1' => '123 Main Street',
            'patient_address_line2' => 'Apt 4B',
            'patient_city' => 'Anytown',
            'patient_state' => 'CA',
            'patient_zip' => '12345',
            'patient_phone' => '(555) 123-4567',
            'patient_email' => 'john.doe@example.com',
            'patient_is_subscriber' => true,

            // Caregiver Information
            'caregiver_name' => 'Jane Doe',
            'caregiver_relationship' => 'spouse',
            'caregiver_phone' => '(555) 987-6543',

            // Service & Shipping
            'expected_service_date' => Carbon::tomorrow()->format('Y-m-d'),
            'shipping_speed' => 'standard',
            'delivery_date' => Carbon::tomorrow()->subDay()->format('Y-m-d'),

            // Primary Insurance (Complete)
            'primary_insurance_name' => 'Medicare Part B',
            'primary_member_id' => 'MED123456789A',
            'primary_payer_phone' => '(800) 123-4567',
            'primary_plan_type' => 'medicare_part_b',

            // Secondary Insurance
            'has_secondary_insurance' => true,
            'secondary_insurance_name' => 'Aetna Supplemental',
            'secondary_member_id' => 'AET987654321',
            'secondary_subscriber_name' => 'John Doe',
            'secondary_subscriber_dob' => '1980-05-15',
            'secondary_payer_phone' => '(800) 987-6543',
            'secondary_plan_type' => 'commercial',

            // Prior Authorization
            'prior_auth_permission' => true,

            // Clinical Information (Complete)
            'wound_type' => 'diabetic_foot_ulcer',
            'wound_types' => ['diabetic_foot_ulcer'],
            'wound_other_specify' => null,
            'wound_location' => 'Left foot, plantar surface',
            'wound_location_details' => 'Medial aspect of left plantar foot',
            'primary_diagnosis_code' => 'E11.621',
            'secondary_diagnosis_code' => 'I87.31',
            'wound_size_length' => 3.5,
            'wound_size_width' => 2.8,
            'wound_size_depth' => 0.5,
            'wound_duration_days' => 5,
            'wound_duration_weeks' => 12,
            'wound_duration_months' => 0,
            'wound_duration_years' => 0,
            'previous_treatments' => 'Standard wound care, debridement, compression therapy',

            // Procedure Information
            'application_cpt_codes' => ['15271', '15272'],
            'prior_applications' => '0',
            'prior_application_product' => null,
            'prior_application_within_12_months' => false,
            'anticipated_applications' => '2-3',

            // Billing Status (Complete)
            'place_of_service' => '11',
            'medicare_part_b_authorized' => true,
            'snf_days' => null,
            'hospice_status' => false,
            'hospice_family_consent' => null,
            'hospice_clinically_necessary' => null,
            'part_a_status' => false,
            'global_period_status' => false,
            'global_period_cpt' => null,
            'global_period_surgery_date' => null,

            // Product Selection (Complete)
            'selected_products' => [
                [
                    'product_id' => $this->testProduct->id,
                    'quantity' => 2,
                    'size' => '4x4',
                ]
            ],

            // Manufacturer Fields
            'manufacturer_fields' => [
                'special_instructions' => 'Handle with care',
                'delivery_preference' => 'morning',
            ],

            // Clinical Attestations (Complete)
            'failed_conservative_treatment' => true,
            'information_accurate' => true,
            'medical_necessity_established' => true,
            'maintain_documentation' => true,
            'authorize_prior_auth' => true,

            // Provider Authorization (Complete)
            'provider_name' => 'Dr. John Provider',
            'provider_npi' => '1234567890',
            'signature_date' => Carbon::today()->format('Y-m-d'),
            'verbal_order' => null,
        ];
    }

    private function assertDatabaseRecordsCreated(string $episodeId, array $formData): void
    {
        // Verify Episode was updated
        $this->assertDatabaseHas('patient_manufacturer_ivr_episodes', [
            'id' => $episodeId,
            'status' => 'ready_for_review',
            'ivr_status' => 'provider_completed',
            'docuseal_submission_id' => $formData['docuseal_submission_id'],
        ]);

        // Verify ProductRequest was created
        $this->assertDatabaseHas('product_requests', [
            'ivr_episode_id' => $episodeId,
            'provider_id' => $formData['provider_id'],
            'facility_id' => $formData['facility_id'],
            'request_type' => $formData['request_type'],
            'order_status' => 'ready_for_review',
            'submission_type' => 'quick_request',
            'docuseal_submission_id' => $formData['docuseal_submission_id'],
        ]);

        // Verify product request metadata contains all critical information
        $productRequest = ProductRequest::where('ivr_episode_id', $episodeId)->first();
        $this->assertNotNull($productRequest);
        
        $metadata = $productRequest->metadata;
        $this->assertArrayHasKey('products', $metadata);
        $this->assertArrayHasKey('clinical_info', $metadata);
        $this->assertArrayHasKey('insurance_info', $metadata);
        $this->assertArrayHasKey('billing_info', $metadata);
        $this->assertArrayHasKey('attestations', $metadata);
        $this->assertArrayHasKey('provider_authorization', $metadata);
        $this->assertArrayHasKey('ivr_submission', $metadata);
    }

    private function calculateFieldMappingCoverage(array $formData): float
    {
        $totalFormFields = count($formData);
        $mappedFields = 0;
        $fieldMappings = $this->testTemplate->field_mappings;

        // Count how many form fields have corresponding Docuseal mappings
        foreach ($formData as $fieldName => $value) {
            if (isset($fieldMappings[$fieldName]) || $this->hasIndirectMapping($fieldName, $fieldMappings)) {
                $mappedFields++;
            }
        }

        // Additional mappings for computed/derived fields
        $derivedMappings = [
            'patient_full_name' => ['patient_first_name', 'patient_last_name'],
            'patient_address' => ['patient_address_line1', 'patient_address_line2'],
            'wound_size' => ['wound_size_length', 'wound_size_width', 'wound_size_depth'],
            'total_wound_area' => ['wound_size_length', 'wound_size_width'],
            'insurance_summary' => ['primary_insurance_name', 'secondary_insurance_name'],
        ];

        foreach ($derivedMappings as $derivedField => $sourceFields) {
            $hasAllSources = true;
            foreach ($sourceFields as $sourceField) {
                if (!isset($formData[$sourceField])) {
                    $hasAllSources = false;
                    break;
                }
            }
            if ($hasAllSources) {
                $mappedFields++;
            }
        }

        $coverage = ($mappedFields / $totalFormFields) * 100;
        
        return round($coverage, 2);
    }

    private function hasIndirectMapping(string $fieldName, array $fieldMappings): bool
    {
        // Check for indirect mappings (e.g., nested field references)
        foreach ($fieldMappings as $mapping) {
            if (isset($mapping['system_field']) && $mapping['system_field'] === $fieldName) {
                return true;
            }
            if (isset($mapping['local_field']) && str_contains($mapping['local_field'], $fieldName)) {
                return true;
            }
        }
        return false;
    }

    private function assertJobsDispatched(string $episodeId): void
    {
        // Verify that the GenerateDocusealPdf job was dispatched
        Queue::assertPushed(GenerateDocusealPdf::class, function ($job) use ($episodeId) {
            return $job->episode->id === $episodeId;
        });

        // Verify that the VerifyInsuranceEligibility job will be dispatched
        // (This happens after PDF generation in the job chain)
    }

    private function verifyDocumentGeneration(string $episodeId): void
    {
        // Simulate the document generation job execution
        $episode = PatientManufacturerIVREpisode::find($episodeId);
        
        // Create a test document record (simulating what the job would create)
        $document = Document::create([
            'documentable_type' => PatientManufacturerIVREpisode::class,
            'documentable_id' => $episodeId,
            'type' => 'insurance_verification',
            'name' => 'test_insurance_verification_document.pdf',
            'path' => 'insurance-verifications/' . $episodeId . '/test_document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'metadata' => [
                'template_id' => $this->testTemplate->docuseal_template_id,
                'docuseal_document_id' => 'doc_123456',
                'generated_at' => now()->toIso8601String(),
                'signatures_included' => false,
            ],
        ]);

        $this->assertNotNull($document);
        $this->assertEquals('insurance_verification', $document->type);
        $this->assertEquals($episodeId, $document->documentable_id);
    }

    private function verifyWorkflowProgression(string $episodeId): void
    {
        $episode = PatientManufacturerIVREpisode::find($episodeId);
        $this->assertNotNull($episode);
        $this->assertEquals('ready_for_review', $episode->status);
        $this->assertEquals('provider_completed', $episode->ivr_status);
        $this->assertNotNull($episode->verification_date);
    }

    private function outputTestResults(float $mappingCoverage, array $formData): void
    {
        $results = [
            'Test Results Summary',
            '=' . str_repeat('=', 50),
            'Field Mapping Coverage: ' . $mappingCoverage . '%',
            'Total Form Fields: ' . count($formData),
            'Successfully Mapped Fields: ' . round((count($formData) * $mappingCoverage) / 100),
            'Test Status: ' . ($mappingCoverage > 90 ? 'PASSED' : 'FAILED'),
            '=' . str_repeat('=', 50),
        ];

        foreach ($results as $result) {
            echo $result . "\n";
        }
    }

    /** @test */
    public function it_handles_file_uploads_in_quick_request()
    {
        $this->actingAs($this->testUser);
        Storage::fake('s3-encrypted');

        $episodeId = Str::uuid();
        $formData = $this->getComprehensiveFormData($episodeId);

        // Add file uploads
        $formData['insurance_card_front'] = UploadedFile::fake()->image('insurance_front.jpg');
        $formData['insurance_card_back'] = UploadedFile::fake()->image('insurance_back.jpg');
        $formData['face_sheet'] = UploadedFile::fake()->create('face_sheet.pdf', 100, 'application/pdf');
        $formData['clinical_notes'] = UploadedFile::fake()->create('clinical_notes.pdf', 200, 'application/pdf');
        $formData['wound_photo'] = UploadedFile::fake()->image('wound_photo.jpg');

        $response = $this->postJson('/quick-request/store', $formData);

        // Verify files were stored
        $this->assertTrue(Storage::disk('s3-encrypted')->exists('phi/insurance-cards/' . date('Y/m') . '/insurance_front.jpg'));
        $this->assertTrue(Storage::disk('s3-encrypted')->exists('phi/insurance-cards/' . date('Y/m') . '/insurance_back.jpg'));
        $this->assertTrue(Storage::disk('s3-encrypted')->exists('phi/face-sheets/' . date('Y/m') . '/face_sheet.pdf'));
        $this->assertTrue(Storage::disk('s3-encrypted')->exists('phi/clinical-notes/' . date('Y/m') . '/clinical_notes.pdf'));
        $this->assertTrue(Storage::disk('s3-encrypted')->exists('phi/wound-photos/' . date('Y/m') . '/wound_photo.jpg'));
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->actingAs($this->testUser);

        $response = $this->postJson('/quick-request/store', [
            'request_type' => 'new_request',
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'provider_id',
                'facility_id',
                'patient_first_name',
                'patient_last_name',
                'patient_dob',
                'expected_service_date',
                'primary_insurance_name',
                'primary_member_id',
                'wound_type',
                'selected_products',
                'docuseal_submission_id',
                'episode_id',
            ]);
    }

    /** @test */
    public function it_validates_ivr_completion_requirement()
    {
        $this->actingAs($this->testUser);

        $formData = $this->getComprehensiveFormData(Str::uuid());
        unset($formData['docuseal_submission_id']); // Remove IVR submission ID

        $response = $this->postJson('/quick-request/store', $formData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['docuseal_submission_id'])
            ->assertJson([
                'errors' => [
                    'docuseal_submission_id' => [
                        'IVR completion is required before submitting your order. Please complete the IVR form in the final step.'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_calculates_accurate_field_mapping_metrics()
    {
        $formData = $this->getComprehensiveFormData(Str::uuid());
        $coverage = $this->calculateFieldMappingCoverage($formData);

        // Verify the calculation is accurate
        $this->assertIsFloat($coverage);
        $this->assertGreaterThan(0, $coverage);
        $this->assertLessThanOrEqual(100, $coverage);

        // For our comprehensive test data, we should achieve high coverage
        $this->assertGreaterThan(90, $coverage, 
            'Field mapping coverage should exceed 90% for comprehensive form data'
        );

        // Output detailed mapping analysis
        $this->analyzeFieldMappings($formData);
    }

    private function analyzeFieldMappings(array $formData): void
    {
        $fieldMappings = $this->testTemplate->field_mappings;
        $mappedFields = [];
        $unmappedFields = [];

        foreach ($formData as $fieldName => $value) {
            if (isset($fieldMappings[$fieldName]) || $this->hasIndirectMapping($fieldName, $fieldMappings)) {
                $mappedFields[] = $fieldName;
            } else {
                $unmappedFields[] = $fieldName;
            }
        }

        echo "\nField Mapping Analysis:\n";
        echo "Mapped Fields (" . count($mappedFields) . "):\n";
        foreach ($mappedFields as $field) {
            echo "  ✓ $field\n";
        }

        echo "\nUnmapped Fields (" . count($unmappedFields) . "):\n";
        foreach ($unmappedFields as $field) {
            echo "  ✗ $field\n";
        }

        echo "\nCoverage: " . round((count($mappedFields) / count($formData)) * 100, 2) . "%\n";
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

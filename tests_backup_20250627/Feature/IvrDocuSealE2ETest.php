<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\Order\ProductRequest;
use App\Models\Order\Product;
use App\Models\User;
use App\Models\Role;
use App\Models\Fhir\Facility;
use App\Models\Order\Manufacturer;
use App\Services\IvrDocusealService;
use App\Services\IvrFieldMappingService;
use App\Services\FhirService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class IvrDocuSealE2ETest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $providerUser;
    protected Facility $facility;
    protected Product $product;
    protected array $mockPatientData;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::create([
            'name' => 'MSC Admin',
            'slug' => 'msc-admin',
            'permissions' => ['manage-orders', 'generate-ivr']
        ]);

        $providerRole = Role::create([
            'name' => 'Provider',
            'slug' => 'provider',
            'permissions' => ['create-product-requests', 'view-product-requests']
        ]);

        // Create users
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'first_name' => 'Test',
            'last_name' => 'Admin'
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->providerUser = User::factory()->create([
            'email' => 'provider@test.com',
            'first_name' => 'Dr. John',
            'last_name' => 'Smith',
            'npi_number' => '1234567890'
        ]);
        $this->providerUser->roles()->attach($providerRole);

        // Create facility
        $this->facility = Facility::create([
            'name' => 'Test Wound Care Center',
            'address' => '123 Medical Plaza',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'phone' => '555-123-4567',
            'npi' => '9876543210',
            'active' => true,
        ]);

        // Create product
        $this->product = Product::create([
            'name' => 'ACELL Cytal Wound Matrix',
            'sku' => 'CWM-2X3',
            'q_code' => 'Q4193',
            'manufacturer' => 'ACZ Distribution',
            'category' => 'Wound Dressings',
            'national_asp' => 125.00,
            'is_active' => true,
            'available_sizes' => ['2x2cm', '2x3cm', '4x4cm', '4x6cm']
        ]);

        // Create manufacturer
        Manufacturer::create([
            'name' => 'ACZ Distribution',
            'contact_email' => 'orders@aczdistribution.com',
            'contact_phone' => '800-123-4567',
            'active' => true
        ]);

        // Mock patient data from FHIR
        $this->mockPatientData = [
            'resourceType' => 'Patient',
            'id' => 'patient-' . $this->faker->uuid,
            'name' => [
                [
                    'given' => ['John'],
                    'family' => 'Doe'
                ]
            ],
            'gender' => 'male',
            'birthDate' => '1970-01-01',
            'address' => [
                [
                    'line' => ['123 Test Street', 'Apt 4B'],
                    'city' => 'Test City',
                    'state' => 'TS',
                    'postalCode' => '12345'
                ]
            ],
            'telecom' => [
                [
                    'system' => 'phone',
                    'value' => '555-987-6543'
                ]
            ]
        ];
    }

    /**
     * Test complete order flow from creation to IVR generation
     */
    public function test_complete_order_flow_with_ivr_generation()
    {
        // Step 1: Create product request as provider
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::create([
            'request_number' => 'REQ-' . date('Ymd') . '-0001',
            'provider_id' => $this->providerUser->id,
            'patient_fhir_id' => $this->mockPatientData['id'],
            'patient_display_id' => 'JODO123',
            'facility_id' => $this->facility->id,
            'payer_name_submitted' => 'Medicare',
            'payer_id' => 'MED123456789',
            'insurance_type' => 'Medicare Part B',
            'expected_service_date' => Carbon::now()->addDays(7),
            'wound_type' => 'diabetic_foot_ulcer',
            'place_of_service' => '11', // Physician Office
            'clinical_summary' => [
                'patient' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                ],
                'woundDetails' => [
                    'type' => 'diabetic_foot_ulcer',
                    'location' => 'left_foot_plantar',
                    'size' => '2.5cm x 3.0cm',
                    'duration' => '6 weeks',
                ],
            ],
            'failed_conservative_treatment' => true,
            'information_accurate' => true,
            'medical_necessity_established' => true,
            'maintain_documentation' => true,
            'authorize_prior_auth' => true,
            'order_status' => 'pending_ivr',
            'ivr_required' => true,
            'total_order_value' => 250.00,
        ]);

        // Attach product with size
        $productRequest->products()->attach($this->product->id, [
            'quantity' => 2,
            'size' => '2x3cm',
            'unit_price' => 125.00,
            'total_price' => 250.00,
        ]);

        $this->assertDatabaseHas('product_requests', [
            'id' => $productRequest->id,
            'order_status' => 'pending_ivr'
        ]);

        // Step 2: Login as admin and generate IVR
        $this->actingAs($this->adminUser);

        // Mock DocuSeal API response
        Http::fake([
            'docuseal.com/api/*' => Http::response([
                [
                    'id' => 12345,
                    'submission_id' => 'sub_' . $this->faker->uuid,
                    'status' => 'completed',
                    'documents' => [
                        [
                            'name' => 'IVR_Form.pdf',
                            'url' => 'https://docuseal.com/documents/sample.pdf'
                        ]
                    ],
                    'created_at' => now()->toIso8601String()
                ]
            ], 200),
        ]);

        // Mock FHIR service to return patient data
        $this->mock(FhirService::class, function ($mock) {
            $mock->shouldReceive('getPatientById')
                ->andReturn($this->mockPatientData);
        });

        // Test IVR field mapping
        $fieldMappingService = app(IvrFieldMappingService::class);
        $mappedFields = $fieldMappingService->mapProductRequestToIvrFields(
            $productRequest,
            'ACZ_Distribution',
            $this->mockPatientData
        );

        // Assert standard fields are mapped correctly
        $this->assertEquals('John', $mappedFields['patient_first_name']);
        $this->assertEquals('Doe', $mappedFields['patient_last_name']);
        $this->assertEquals('1970-01-01', $mappedFields['patient_dob']);
        $this->assertEquals('JODO123', $mappedFields['patient_display_id']);
        $this->assertEquals('Medicare', $mappedFields['payer_name']);
        $this->assertEquals('MED123456789', $mappedFields['payer_id']);
        $this->assertEquals('ACELL Cytal Wound Matrix', $mappedFields['product_name']);
        $this->assertEquals('Q4193', $mappedFields['product_code']);
        $this->assertEquals('ACZ Distribution', $mappedFields['manufacturer']);
        $this->assertEquals('2x3cm', $mappedFields['size']);
        $this->assertEquals(2, $mappedFields['quantity']);
        $this->assertEquals('Dr. John Smith', $mappedFields['provider_name']);
        $this->assertEquals('1234567890', $mappedFields['provider_npi']);
        $this->assertEquals('Test Wound Care Center', $mappedFields['facility_name']);
        $this->assertEquals('Yes', $mappedFields['failed_conservative_treatment']);
        $this->assertEquals('Yes', $mappedFields['medical_necessity_established']);
        $this->assertEquals('Physician Office', $mappedFields['place_of_service']);

        // Test IVR generation via API
        $response = $this->postJson(route('admin.orders.generate-ivr', $productRequest->id), [
            'ivr_required' => true
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('product_requests', [
            'id' => $productRequest->id,
            'order_status' => 'ivr_sent'
        ]);

        // Verify DocuSeal submission was created
        $this->assertDatabaseHas('docuseal_submissions', [
            'order_id' => $productRequest->id,
            'document_type' => 'IVR',
            'status' => 'completed'
        ]);
    }

    /**
     * Test IVR field mapping for different manufacturers
     */
    public function test_manufacturer_specific_field_mapping()
    {
        $manufacturers = [
            'ACZ_Distribution' => [
                'physician_attestation' => 'Yes',
                'not_used_previously' => 'Yes'
            ],
            'Advanced_Health' => [
                'multiple_products' => 'No',
                'previous_use' => 'No'
            ],
            'MedLife' => [
                'amnio_amp_size' => '2x2'
            ],
            'Centurion' => [
                'previous_amnion_use' => 'No',
                'stat_order' => 'No'
            ],
            'BioWerX' => [
                'first_application' => 'Yes',
                'reapplication' => 'No'
            ],
        ];

        foreach ($manufacturers as $manufacturerKey => $expectedFields) {
            // Create product for this manufacturer
            $product = Product::create([
                'name' => "{$manufacturerKey} Product",
                'sku' => "TEST-{$manufacturerKey}",
                'manufacturer' => str_replace('_', ' ', $manufacturerKey),
                'is_active' => true,
            ]);

            $productRequest = ProductRequest::factory()->create([
                'provider_id' => $this->providerUser->id,
                'facility_id' => $this->facility->id,
                'order_status' => 'pending_ivr',
            ]);

            $productRequest->products()->attach($product->id, [
                'quantity' => 1,
                'size' => '2x2cm',
            ]);

            $fieldMappingService = app(IvrFieldMappingService::class);
            $mappedFields = $fieldMappingService->mapProductRequestToIvrFields(
                $productRequest,
                $manufacturerKey,
                $this->mockPatientData
            );

            // Assert manufacturer-specific fields
            foreach ($expectedFields as $field => $expectedValue) {
                $this->assertArrayHasKey($field, $mappedFields);
                $this->assertEquals($expectedValue, $mappedFields[$field]);
            }
        }
    }

    /**
     * Test IVR generation with missing required fields
     */
    public function test_ivr_generation_validates_required_fields()
    {
        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'facility_id' => $this->facility->id,
            'order_status' => 'pending_ivr',
            'payer_name_submitted' => null, // Missing required field
        ]);

        $productRequest->products()->attach($this->product->id, [
            'quantity' => 1,
            'size' => '2x2cm',
        ]);

        $fieldMappingService = app(IvrFieldMappingService::class);
        $mappedFields = $fieldMappingService->mapProductRequestToIvrFields(
            $productRequest,
            'ACZ_Distribution',
            []
        );

        $errors = $fieldMappingService->validateMapping('ACZ_Distribution', $mappedFields);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Missing required field: payer_name', $errors);
    }

    /**
     * Test IVR skip functionality
     */
    public function test_ivr_skip_with_justification()
    {
        $this->actingAs($this->adminUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'facility_id' => $this->facility->id,
            'order_status' => 'pending_ivr',
        ]);

        $response = $this->postJson(route('admin.orders.generate-ivr', $productRequest->id), [
            'ivr_required' => false,
            'justification' => 'Emergency order - IVR to follow'
        ]);

        $response->assertRedirect();
        
        $productRequest->refresh();
        $this->assertEquals('Emergency order - IVR to follow', $productRequest->ivr_bypass_reason);
        $this->assertFalse($productRequest->ivr_required);
    }

    /**
     * Test send IVR to manufacturer
     */
    public function test_send_ivr_to_manufacturer()
    {
        $this->actingAs($this->adminUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'facility_id' => $this->facility->id,
            'order_status' => 'ivr_sent',
            'ivr_sent_at' => now(),
            'docuseal_submission_id' => 'sub_test123',
        ]);

        $response = $this->postJson(route('admin.orders.send-ivr-to-manufacturer', $productRequest->id));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'IVR sent to manufacturer successfully'
            ]);

        $productRequest->refresh();
        $this->assertNotNull($productRequest->manufacturer_sent_at);
        $this->assertEquals($this->adminUser->id, $productRequest->manufacturer_sent_by);
    }

    /**
     * Test manufacturer approval confirmation
     */
    public function test_confirm_manufacturer_approval()
    {
        $this->actingAs($this->adminUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'facility_id' => $this->facility->id,
            'order_status' => 'ivr_sent',
            'manufacturer_sent_at' => now(),
        ]);

        $response = $this->postJson(route('admin.orders.manufacturer-approval', $productRequest->id), [
            'approved' => true,
            'reference' => 'MFG-APPROVAL-123',
            'notes' => 'Approved via phone confirmation'
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'order_status' => 'ivr_confirmed'
            ]);

        $productRequest->refresh();
        $this->assertEquals('ivr_confirmed', $productRequest->order_status);
        $this->assertTrue($productRequest->manufacturer_approved);
        $this->assertEquals('MFG-APPROVAL-123', $productRequest->manufacturer_approval_reference);
    }

    /**
     * Test field formatting for different data types
     */
    public function test_field_type_formatting()
    {
        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'facility_id' => $this->facility->id,
            'expected_service_date' => '2024-12-25',
            'clinical_summary' => [
                'woundDetails' => [
                    'size' => '2.5cm x 3.0cm',
                    'duration' => '6 weeks'
                ]
            ]
        ]);

        $fieldMappingService = app(IvrFieldMappingService::class);
        $mappedFields = $fieldMappingService->mapProductRequestToIvrFields(
            $productRequest,
            'ACZ_Distribution',
            $this->mockPatientData
        );

        // Test date formatting
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $mappedFields['expected_service_date']);
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $mappedFields['todays_date']);
        
        // Test time formatting
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2} [AP]M$/', $mappedFields['current_time']);
        
        // Test checkbox formatting (Yes/No)
        $this->assertContains($mappedFields['failed_conservative_treatment'], ['Yes', 'No']);
        
        // Test wound details extraction
        $this->assertEquals('2.5cm x 3.0cm', $mappedFields['wound_size']);
        $this->assertEquals('6 weeks', $mappedFields['wound_duration']);
    }

    /**
     * Test complete workflow permissions
     */
    public function test_workflow_permissions()
    {
        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'facility_id' => $this->facility->id,
            'order_status' => 'pending_ivr',
        ]);

        // Provider cannot generate IVR
        $this->actingAs($this->providerUser);
        $response = $this->postJson(route('admin.orders.generate-ivr', $productRequest->id));
        $response->assertForbidden();

        // Admin can generate IVR
        $this->actingAs($this->adminUser);
        
        Http::fake([
            'docuseal.com/api/*' => Http::response([
                [
                    'id' => 12345,
                    'submission_id' => 'sub_test',
                    'status' => 'completed',
                    'documents' => [['url' => 'https://example.com/doc.pdf']]
                ]
            ], 200),
        ]);

        $this->mock(FhirService::class, function ($mock) {
            $mock->shouldReceive('getPatientById')->andReturn($this->mockPatientData);
        });

        $response = $this->postJson(route('admin.orders.generate-ivr', $productRequest->id), [
            'ivr_required' => true
        ]);
        
        $response->assertRedirect();
    }
}
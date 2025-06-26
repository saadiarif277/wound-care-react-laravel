<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\Order\ProductRequest;
use App\Models\Order\Product;
use App\Models\Order\Manufacturer;
use App\Models\Order\Category;
use App\Models\User;
use App\Models\Role;
use App\Models\Fhir\Facility;
use App\Models\Fhir\Patient;
use App\Models\Fhir\Practitioner;
use App\Models\Insurance\EligibilityCheck;
use App\Models\Insurance\PreAuthorization;
use App\Models\Insurance\MedicareMacValidation;
use App\Services\FhirService;
use App\Services\EligibilityEngine\EligibilityService;
use App\Services\MacValidationService;
use App\Services\ProductRecommendationEngine\MSCProductRecommendationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProductRequestPatientE2ETest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $providerUser;
    protected User $adminUser;
    protected Facility $facility;
    protected array $products;
    protected array $mockPatientData;
    protected array $mockProviderData;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $providerRole = Role::create([
            'name' => 'Provider',
            'slug' => 'provider',
            'permissions' => ['create-product-requests', 'view-product-requests']
        ]);

        $adminRole = Role::create([
            'name' => 'MSC Admin',
            'slug' => 'msc-admin',
            'permissions' => ['manage-orders', 'view-all-orders']
        ]);

        // Create users
        $this->providerUser = User::factory()->create([
            'email' => 'provider@test.com',
            'first_name' => 'Dr. Sarah',
            'last_name' => 'Johnson',
            'npi_number' => '1234567890'
        ]);
        $this->providerUser->roles()->attach($providerRole);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'first_name' => 'Admin',
            'last_name' => 'User'
        ]);
        $this->adminUser->roles()->attach($adminRole);

        // Create facility
        $this->facility = Facility::create([
            'name' => 'Advanced Wound Care Center',
            'address' => '123 Medical Plaza',
            'city' => 'Miami',
            'state' => 'FL',
            'zip_code' => '33101',
            'phone' => '305-555-1234',
            'npi' => '9876543210',
            'active' => true,
        ]);

        // Create categories and manufacturers
        $woundDressingCategory = Category::create([
            'name' => 'Wound Dressings',
            'description' => 'Advanced wound care dressings'
        ]);

        $manufacturer1 = Manufacturer::create([
            'name' => 'ACZ Distribution',
            'contact_email' => 'orders@aczdistribution.com',
            'contact_phone' => '800-123-4567',
            'active' => true
        ]);

        $manufacturer2 = Manufacturer::create([
            'name' => 'Advanced Health',
            'contact_email' => 'orders@advancedhealth.com',
            'contact_phone' => '800-234-5678',
            'active' => true
        ]);

        // Create products
        $this->products = [
            Product::create([
                'name' => 'ACELL Cytal Wound Matrix',
                'sku' => 'CWM-2X3',
                'q_code' => 'Q4193',
                'manufacturer' => 'ACZ Distribution',
                'category' => 'Wound Dressings',
                'national_asp' => 125.00,
                'is_active' => true,
                'available_sizes' => ['2x2cm', '2x3cm', '4x4cm', '4x6cm']
            ]),
            Product::create([
                'name' => 'AmnioBand Membrane',
                'sku' => 'AB-3X3',
                'q_code' => 'Q4188',
                'manufacturer' => 'Advanced Health',
                'category' => 'Wound Dressings',
                'national_asp' => 150.00,
                'is_active' => true,
                'available_sizes' => ['3x3cm', '4x4cm', '5x5cm']
            ])
        ];

        // Mock patient data
        $this->mockPatientData = [
            'resourceType' => 'Patient',
            'id' => 'patient-' . $this->faker->uuid,
            'identifier' => [
                [
                    'system' => 'http://hospital.smarthealthit.org',
                    'value' => 'JODO123'
                ]
            ],
            'name' => [
                [
                    'given' => ['John'],
                    'family' => 'Doe'
                ]
            ],
            'gender' => 'male',
            'birthDate' => '1955-06-15',
            'address' => [
                [
                    'line' => ['456 Oak Street'],
                    'city' => 'Miami',
                    'state' => 'FL',
                    'postalCode' => '33125'
                ]
            ],
            'telecom' => [
                [
                    'system' => 'phone',
                    'value' => '305-555-9876'
                ]
            ]
        ];

        // Mock provider data
        $this->mockProviderData = [
            'resourceType' => 'Practitioner',
            'id' => 'practitioner-' . $this->faker->uuid,
            'identifier' => [
                [
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => '1234567890'
                ]
            ],
            'name' => [
                [
                    'given' => ['Sarah'],
                    'family' => 'Johnson',
                    'prefix' => ['Dr.']
                ]
            ],
            'telecom' => [
                [
                    'system' => 'phone',
                    'value' => '305-555-1234'
                ]
            ]
        ];
    }

    /**
     * Test complete product request flow from patient info to order completion
     */
    public function test_complete_product_request_patient_flow()
    {
        $this->actingAs($this->providerUser);

        // Step 1: Start product request and enter patient information
        $response = $this->postJson('/api/product-request-patient/create', [
            'patient' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'dateOfBirth' => '1955-06-15',
                'gender' => 'male',
                'phone' => '305-555-9876',
                'address' => '456 Oak Street',
                'city' => 'Miami',
                'state' => 'FL',
                'zipCode' => '33125'
            ],
            'provider' => [
                'firstName' => 'Sarah',
                'lastName' => 'Johnson',
                'npi' => '1234567890',
                'phone' => '305-555-1234'
            ],
            'facility_id' => $this->facility->id
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'request_number',
                    'patient_display_id',
                    'status'
                ]
            ]);

        $productRequestId = $response->json('data.id');

        // Step 2: Add insurance information
        $response = $this->postJson("/api/product-request-patient/{$productRequestId}/insurance", [
            'insurance' => [
                'payer_name' => 'Medicare',
                'payer_id' => 'MED123456789',
                'insurance_type' => 'Medicare Part B',
                'group_number' => null,
                'relationship' => 'self'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Insurance information updated successfully'
            ]);

        // Step 3: Verify eligibility (mocked)
        Http::fake([
            'availity.com/api/*' => Http::response([
                'eligible' => true,
                'coverage_active' => true,
                'deductible_met' => 850.00,
                'deductible_remaining' => 150.00,
                'copay_amount' => 20.00,
                'coverage_details' => [
                    'wound_care_covered' => true,
                    'prior_auth_required' => false
                ]
            ], 200)
        ]);

        $response = $this->postJson("/api/product-request-patient/{$productRequestId}/verify-eligibility");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'eligible' => true,
                'coverage_active' => true
            ]);

        // Step 4: Add wound information
        $response = $this->postJson("/api/product-request-patient/{$productRequestId}/wound-info", [
            'wound' => [
                'type' => 'diabetic_foot_ulcer',
                'location' => 'left_foot_plantar',
                'size' => '2.5cm x 3.0cm',
                'depth' => '0.5cm',
                'duration' => '8 weeks',
                'previous_treatments' => ['saline_gauze', 'hydrocolloid'],
                'failed_conservative_treatment' => true
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Wound information updated successfully'
            ]);

        // Step 5: Get product recommendations
        $response = $this->getJson("/api/product-request-patient/{$productRequestId}/recommendations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'recommendations' => [
                        '*' => [
                            'product_id',
                            'product_name',
                            'q_code',
                            'manufacturer',
                            'score',
                            'reasons'
                        ]
                    ],
                    'mac_validation' => [
                        'is_valid',
                        'mac_region',
                        'requirements'
                    ]
                ]
            ]);

        // Step 6: Select products
        $response = $this->postJson("/api/product-request-patient/{$productRequestId}/products", [
            'products' => [
                [
                    'product_id' => $this->products[0]->id,
                    'quantity' => 2,
                    'size' => '2x3cm'
                ]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Products added successfully'
            ]);

        // Step 7: Complete attestations
        $response = $this->postJson("/api/product-request-patient/{$productRequestId}/attestations", [
            'attestations' => [
                'information_accurate' => true,
                'medical_necessity_established' => true,
                'maintain_documentation' => true,
                'authorize_prior_auth' => true
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Attestations recorded successfully'
            ]);

        // Step 8: Submit order
        $response = $this->postJson("/api/product-request-patient/{$productRequestId}/submit");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product request submitted successfully',
                'data' => [
                    'order_status' => 'pending_ivr'
                ]
            ]);

        // Verify database state
        $this->assertDatabaseHas('product_requests', [
            'id' => $productRequestId,
            'order_status' => 'pending_ivr',
            'provider_id' => $this->providerUser->id,
            'facility_id' => $this->facility->id
        ]);

        $this->assertDatabaseHas('product_request_products', [
            'product_request_id' => $productRequestId,
            'product_id' => $this->products[0]->id,
            'quantity' => 2,
            'size' => '2x3cm'
        ]);
    }

    /**
     * Test patient search functionality
     */
    public function test_patient_search_integration()
    {
        $this->actingAs($this->providerUser);

        // Mock FHIR service
        $this->mock(FhirService::class, function ($mock) {
            $mock->shouldReceive('searchPatients')
                ->with('Doe', 'John', '1955-06-15')
                ->andReturn([
                    $this->mockPatientData
                ]);
        });

        $response = $this->getJson('/api/product-request-patient/search-patients?' . http_build_query([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'dateOfBirth' => '1955-06-15'
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'display_id',
                        'name',
                        'birthDate',
                        'gender',
                        'address'
                    ]
                ]
            ]);
    }

    /**
     * Test Medicare MAC validation
     */
    public function test_medicare_mac_validation()
    {
        $this->actingAs($this->providerUser);

        // Create a product request with Medicare
        $productRequest = ProductRequest::create([
            'request_number' => 'REQ-' . date('Ymd') . '-0001',
            'provider_id' => $this->providerUser->id,
            'patient_fhir_id' => $this->mockPatientData['id'],
            'patient_display_id' => 'JODO123',
            'facility_id' => $this->facility->id,
            'payer_name_submitted' => 'Medicare',
            'insurance_type' => 'Medicare Part B',
            'clinical_summary' => [
                'patient' => [
                    'address' => [
                        'zipCode' => '33125' // Florida ZIP
                    ]
                ],
                'wound' => [
                    'type' => 'diabetic_foot_ulcer'
                ]
            ],
            'order_status' => 'draft'
        ]);

        $response = $this->getJson("/api/product-request-patient/{$productRequest->id}/validate-mac");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_valid' => true,
                    'mac_region' => 'First Coast Service Options',
                    'requirements' => [
                        'documentation_required' => true,
                        'specific_forms' => ['wound_measurement', 'failed_conservative_treatment']
                    ]
                ]
            ]);
    }

    /**
     * Test validation errors for incomplete data
     */
    public function test_validation_errors_for_incomplete_submission()
    {
        $this->actingAs($this->providerUser);

        // Create incomplete product request
        $response = $this->postJson('/api/product-request-patient/create', [
            'patient' => [
                'firstName' => 'John',
                // Missing required fields
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'patient.lastName',
                'patient.dateOfBirth',
                'patient.gender'
            ]);
    }

    /**
     * Test concurrent user access
     */
    public function test_concurrent_user_access_handling()
    {
        $this->actingAs($this->providerUser);

        // Create a product request
        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        // Simulate another user trying to access
        $anotherProvider = User::factory()->create();
        $anotherProvider->roles()->attach(Role::where('slug', 'provider')->first());

        $this->actingAs($anotherProvider);

        $response = $this->getJson("/api/product-request-patient/{$productRequest->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to access this product request'
            ]);
    }

    /**
     * Test order history tracking
     */
    public function test_order_action_history_tracking()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        // Update insurance
        $this->postJson("/api/product-request-patient/{$productRequest->id}/insurance", [
            'insurance' => [
                'payer_name' => 'Medicare',
                'payer_id' => 'MED123456789'
            ]
        ]);

        // Add wound info
        $this->postJson("/api/product-request-patient/{$productRequest->id}/wound-info", [
            'wound' => [
                'type' => 'diabetic_foot_ulcer',
                'location' => 'left_foot'
            ]
        ]);

        // Check history
        $response = $this->getJson("/api/product-request-patient/{$productRequest->id}/history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'action',
                        'description',
                        'user_name',
                        'created_at'
                    ]
                ]
            ]);

        $history = $response->json('data');
        $this->assertCount(2, $history);
        $this->assertEquals('insurance_updated', $history[0]['action']);
        $this->assertEquals('wound_info_updated', $history[1]['action']);
    }

    /**
     * Test auto-save functionality
     */
    public function test_auto_save_draft_functionality()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        // Auto-save patient info
        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/auto-save", [
            'section' => 'patient',
            'data' => [
                'firstName' => 'John',
                'lastName' => 'Doe'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Draft saved'
            ]);

        // Verify data was saved
        $productRequest->refresh();
        $this->assertEquals('John', $productRequest->clinical_summary['patient']['firstName']);
        $this->assertEquals('Doe', $productRequest->clinical_summary['patient']['lastName']);
    }

    /**
     * Test PDF generation for completed orders
     */
    public function test_order_summary_pdf_generation()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'submitted',
            'clinical_summary' => [
                'patient' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe'
                ],
                'wound' => [
                    'type' => 'diabetic_foot_ulcer'
                ]
            ]
        ]);

        $productRequest->products()->attach($this->products[0]->id, [
            'quantity' => 2,
            'size' => '2x3cm'
        ]);

        $response = $this->getJson("/api/product-request-patient/{$productRequest->id}/download-summary");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test error recovery and retry mechanisms
     */
    public function test_error_recovery_mechanisms()
    {
        $this->actingAs($this->providerUser);

        // Simulate eligibility check failure
        Http::fake([
            'availity.com/api/*' => Http::sequence()
                ->push(null, 500) // First attempt fails
                ->push([ // Second attempt succeeds
                    'eligible' => true,
                    'coverage_active' => true
                ], 200)
        ]);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'payer_name_submitted' => 'Medicare'
        ]);

        // First attempt should fail gracefully
        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/verify-eligibility");

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Eligibility check failed. Please try again.',
                'can_retry' => true
            ]);

        // Retry should succeed
        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/verify-eligibility");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'eligible' => true
            ]);
    }
}

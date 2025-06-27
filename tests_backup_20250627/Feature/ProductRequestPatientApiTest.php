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
use App\Services\FhirService;
use App\Services\EligibilityEngine\AvailityEligibilityService;
use App\Services\MacValidationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ProductRequestPatientApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $providerUser;
    protected Facility $facility;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create provider role and user
        $providerRole = Role::create([
            'name' => 'Provider',
            'slug' => 'provider',
            'permissions' => ['create-product-requests', 'view-product-requests']
        ]);

        $this->providerUser = User::factory()->create([
            'email' => 'provider@test.com',
            'first_name' => 'Dr. Test',
            'last_name' => 'Provider',
            'npi_number' => '1234567890'
        ]);
        $this->providerUser->roles()->attach($providerRole);

        // Create facility
        $this->facility = Facility::create([
            'name' => 'Test Facility',
            'address' => '123 Test St',
            'city' => 'Miami',
            'state' => 'FL',
            'zip_code' => '33101',
            'phone' => '305-555-1234',
            'npi' => '9876543210',
            'active' => true,
        ]);

        // Create product
        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'q_code' => 'Q4193',
            'manufacturer' => 'Test Manufacturer',
            'category' => 'Wound Dressings',
            'national_asp' => 100.00,
            'is_active' => true,
            'available_sizes' => ['2x2cm', '3x3cm']
        ]);
    }

    /**
     * Test API authentication requirements
     */
    public function test_api_requires_authentication()
    {
        $response = $this->postJson('/api/product-request-patient/create', []);
        $response->assertStatus(401);

        $response = $this->getJson('/api/product-request-patient/1');
        $response->assertStatus(401);

        $response = $this->postJson('/api/product-request-patient/1/insurance', []);
        $response->assertStatus(401);
    }

    /**
     * Test create product request API endpoint
     */
    public function test_create_product_request_api()
    {
        $this->actingAs($this->providerUser);

        $payload = [
            'patient' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'dateOfBirth' => '1960-01-15',
                'gender' => 'male',
                'phone' => '305-555-9876',
                'address' => '456 Oak Street',
                'city' => 'Miami',
                'state' => 'FL',
                'zipCode' => '33125'
            ],
            'provider' => [
                'firstName' => 'Test',
                'lastName' => 'Provider',
                'npi' => '1234567890',
                'phone' => '305-555-1234'
            ],
            'facility_id' => $this->facility->id
        ];

        $response = $this->postJson('/api/product-request-patient/create', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'request_number',
                    'patient_display_id',
                    'status',
                    'clinical_summary',
                    'created_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'draft'
                ]
            ]);

        $productRequestId = $response->json('data.id');

        // Verify database
        $this->assertDatabaseHas('product_requests', [
            'id' => $productRequestId,
            'provider_id' => $this->providerUser->id,
            'facility_id' => $this->facility->id,
            'order_status' => 'draft'
        ]);
    }

    /**
     * Test update insurance information API
     */
    public function test_update_insurance_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        $payload = [
            'insurance' => [
                'payer_name' => 'Blue Cross Blue Shield',
                'payer_id' => 'BCBS123456',
                'insurance_type' => 'PPO',
                'group_number' => 'GRP123',
                'relationship' => 'self'
            ]
        ];

        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/insurance", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Insurance information updated successfully'
            ]);

        $productRequest->refresh();
        $this->assertEquals('Blue Cross Blue Shield', $productRequest->payer_name_submitted);
        $this->assertEquals('BCBS123456', $productRequest->payer_id);
        $this->assertEquals('PPO', $productRequest->insurance_type);
    }

    /**
     * Test eligibility verification API with mocked external service
     */
    public function test_verify_eligibility_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'payer_name_submitted' => 'Medicare',
            'payer_id' => 'MED123456',
            'order_status' => 'draft'
        ]);

        // Mock Availity API response
        Http::fake([
            'availity.com/api/*' => Http::response([
                'eligible' => true,
                'coverage_active' => true,
                'deductible_met' => 1000.00,
                'deductible_remaining' => 0.00,
                'copay_amount' => 20.00,
                'coverage_details' => [
                    'wound_care_covered' => true,
                    'prior_auth_required' => false,
                    'coverage_percentage' => 80
                ]
            ], 200)
        ]);

        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/verify-eligibility");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'eligible' => true,
                'coverage_active' => true,
                'coverage_details' => [
                    'wound_care_covered' => true,
                    'prior_auth_required' => false,
                    'coverage_percentage' => 80
                ]
            ]);

        // Verify eligibility check was saved
        $this->assertDatabaseHas('eligibility_checks', [
            'product_request_id' => $productRequest->id,
            'is_eligible' => true,
            'coverage_active' => true
        ]);
    }

    /**
     * Test wound information update API
     */
    public function test_update_wound_info_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        $payload = [
            'wound' => [
                'type' => 'pressure_ulcer',
                'location' => 'sacral_region',
                'size' => '4.0cm x 5.0cm',
                'depth' => '1.0cm',
                'duration' => '12 weeks',
                'stage' => 'stage_3',
                'previous_treatments' => ['foam_dressing', 'hydrogel'],
                'failed_conservative_treatment' => true,
                'infection_present' => false,
                'drainage_type' => 'moderate_serous'
            ]
        ];

        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/wound-info", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Wound information updated successfully'
            ]);

        $productRequest->refresh();
        $this->assertEquals('pressure_ulcer', $productRequest->wound_type);
        $this->assertEquals('sacral_region', $productRequest->clinical_summary['wound']['location']);
        $this->assertEquals('4.0cm x 5.0cm', $productRequest->clinical_summary['wound']['size']);
    }

    /**
     * Test product recommendations API
     */
    public function test_get_product_recommendations_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'wound_type' => 'diabetic_foot_ulcer',
            'payer_name_submitted' => 'Medicare',
            'clinical_summary' => [
                'wound' => [
                    'type' => 'diabetic_foot_ulcer',
                    'size' => '2.5cm x 3.0cm',
                    'depth' => '0.5cm',
                    'location' => 'left_foot_plantar'
                ],
                'patient' => [
                    'address' => [
                        'zipCode' => '33125'
                    ]
                ]
            ],
            'order_status' => 'draft'
        ]);

        $response = $this->getJson("/api/product-request-patient/{$productRequest->id}/recommendations");

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
                            'reasons',
                            'available_sizes',
                            'price'
                        ]
                    ],
                    'mac_validation' => [
                        'is_valid',
                        'mac_region',
                        'requirements'
                    ]
                ]
            ]);

        $recommendations = $response->json('data.recommendations');
        $this->assertNotEmpty($recommendations);
        $this->assertGreaterThanOrEqual(0, $recommendations[0]['score']);
        $this->assertLessThanOrEqual(100, $recommendations[0]['score']);
    }

    /**
     * Test add products to request API
     */
    public function test_add_products_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        $payload = [
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'size' => '2x2cm'
                ]
            ]
        ];

        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/products", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Products added successfully',
                'data' => [
                    'total_order_value' => 300.00 // 3 * 100.00
                ]
            ]);

        $this->assertDatabaseHas('product_request_products', [
            'product_request_id' => $productRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'size' => '2x2cm',
            'unit_price' => 100.00,
            'total_price' => 300.00
        ]);
    }

    /**
     * Test attestations API
     */
    public function test_attestations_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        $payload = [
            'attestations' => [
                'information_accurate' => true,
                'medical_necessity_established' => true,
                'maintain_documentation' => true,
                'authorize_prior_auth' => true
            ]
        ];

        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/attestations", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Attestations recorded successfully'
            ]);

        $productRequest->refresh();
        $this->assertTrue($productRequest->information_accurate);
        $this->assertTrue($productRequest->medical_necessity_established);
        $this->assertTrue($productRequest->maintain_documentation);
        $this->assertTrue($productRequest->authorize_prior_auth);
    }

    /**
     * Test submit order API
     */
    public function test_submit_order_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft',
            'information_accurate' => true,
            'medical_necessity_established' => true,
            'maintain_documentation' => true,
            'authorize_prior_auth' => true,
            'payer_name_submitted' => 'Medicare',
            'wound_type' => 'diabetic_foot_ulcer'
        ]);

        // Add a product
        $productRequest->products()->attach($this->product->id, [
            'quantity' => 2,
            'size' => '3x3cm',
            'unit_price' => 100.00,
            'total_price' => 200.00
        ]);

        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/submit");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product request submitted successfully',
                'data' => [
                    'order_status' => 'pending_ivr',
                    'ivr_required' => true
                ]
            ]);

        $productRequest->refresh();
        $this->assertEquals('pending_ivr', $productRequest->order_status);
        $this->assertTrue($productRequest->ivr_required);
        $this->assertNotNull($productRequest->submitted_at);
    }

    /**
     * Test auto-save API
     */
    public function test_auto_save_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        $payload = [
            'section' => 'patient',
            'data' => [
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'phone' => '305-555-1111'
            ]
        ];

        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/auto-save", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Draft saved',
                'last_saved' => true
            ]);

        $productRequest->refresh();
        $this->assertEquals('Jane', $productRequest->clinical_summary['patient']['firstName']);
        $this->assertEquals('Smith', $productRequest->clinical_summary['patient']['lastName']);
        $this->assertEquals('305-555-1111', $productRequest->clinical_summary['patient']['phone']);
    }

    /**
     * Test order history API
     */
    public function test_order_history_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        // Create some history
        $productRequest->actionHistory()->create([
            'action' => 'created',
            'description' => 'Order created',
            'user_id' => $this->providerUser->id,
            'metadata' => []
        ]);

        $productRequest->actionHistory()->create([
            'action' => 'insurance_updated',
            'description' => 'Insurance information added',
            'user_id' => $this->providerUser->id,
            'metadata' => ['payer' => 'Medicare']
        ]);

        $response = $this->getJson("/api/product-request-patient/{$productRequest->id}/history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'action',
                        'description',
                        'user_name',
                        'created_at',
                        'metadata'
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test MAC validation API
     */
    public function test_mac_validation_api()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'payer_name_submitted' => 'Medicare',
            'clinical_summary' => [
                'patient' => [
                    'address' => [
                        'zipCode' => '90210' // California ZIP
                    ]
                ],
                'wound' => [
                    'type' => 'venous_ulcer'
                ]
            ],
            'order_status' => 'draft'
        ]);

        $response = $this->getJson("/api/product-request-patient/{$productRequest->id}/validate-mac");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'is_valid',
                    'mac_region',
                    'requirements' => [
                        'documentation_required',
                        'specific_forms',
                        'additional_notes'
                    ]
                ]
            ]);

        $macValidation = $response->json('data');
        $this->assertNotEmpty($macValidation['mac_region']);
        $this->assertIsBool($macValidation['is_valid']);
    }

    /**
     * Test API rate limiting
     */
    public function test_api_rate_limiting()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        // Make multiple requests to trigger rate limiting
        for ($i = 0; $i < 60; $i++) {
            $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/auto-save", [
                'section' => 'patient',
                'data' => ['firstName' => "Test{$i}"]
            ]);
        }

        // The 61st request should be rate limited
        $response = $this->postJson("/api/product-request-patient/{$productRequest->id}/auto-save", [
            'section' => 'patient',
            'data' => ['firstName' => 'RateLimited']
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    /**
     * Test concurrent request handling
     */
    public function test_concurrent_request_handling()
    {
        $this->actingAs($this->providerUser);

        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id,
            'order_status' => 'draft'
        ]);

        // Simulate concurrent updates
        $promises = [];

        // Update 1: Insurance
        $response1 = $this->postJson("/api/product-request-patient/{$productRequest->id}/insurance", [
            'insurance' => [
                'payer_name' => 'Aetna',
                'payer_id' => 'AET123'
            ]
        ]);

        // Update 2: Wound info
        $response2 = $this->postJson("/api/product-request-patient/{$productRequest->id}/wound-info", [
            'wound' => [
                'type' => 'surgical_wound',
                'location' => 'abdomen'
            ]
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $productRequest->refresh();
        $this->assertEquals('Aetna', $productRequest->payer_name_submitted);
        $this->assertEquals('surgical_wound', $productRequest->wound_type);
    }

    /**
     * Test API error handling
     */
    public function test_api_error_handling()
    {
        $this->actingAs($this->providerUser);

        // Test non-existent product request
        $response = $this->getJson('/api/product-request-patient/99999');
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Product request not found'
            ]);

        // Test invalid data
        $response = $this->postJson('/api/product-request-patient/create', [
            'patient' => [
                'firstName' => '', // Empty required field
                'dateOfBirth' => 'invalid-date'
            ]
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['patient.firstName', 'patient.dateOfBirth']);

        // Test unauthorized access
        $anotherUser = User::factory()->create();
        $productRequest = ProductRequest::factory()->create([
            'provider_id' => $this->providerUser->id
        ]);

        $this->actingAs($anotherUser);
        $response = $this->getJson("/api/product-request-patient/{$productRequest->id}");
        $response->assertStatus(403);
    }
}

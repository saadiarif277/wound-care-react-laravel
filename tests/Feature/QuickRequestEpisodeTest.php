<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Episode;
use App\Models\Manufacturer;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;

class QuickRequestEpisodeTest extends TestCase
{
    use RefreshDatabase;

    protected QuickRequestOrchestrator $orchestrator;
    protected User $provider;
    protected Manufacturer $manufacturer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->provider = User::factory()->create([
            'role' => 'provider',
            'npi' => '1234567890'
        ]);

        $this->manufacturer = Manufacturer::factory()->create([
            'name' => 'Test Manufacturer'
        ]);

        // Mock FHIR responses
        Http::fake([
            '*/Patient' => Http::response(['id' => 'test-patient-123'], 201),
            '*/Practitioner' => Http::response(['id' => 'test-practitioner-456'], 201),
            '*/Organization' => Http::response(['id' => 'test-org-789'], 201),
            '*/Condition' => Http::response(['id' => 'test-condition-001'], 201),
            '*/EpisodeOfCare' => Http::response(['id' => 'test-episode-002'], 201),
            '*/Coverage' => Http::response(['id' => 'test-coverage-003'], 201),
            '*/Task' => Http::response(['id' => 'test-task-004'], 201),
        ]);
    }

    /** @test */
    public function it_creates_new_episode_with_initial_order()
    {
        $this->actingAs($this->provider);

        $response = $this->postJson('/api/v1/quick-request/episodes', [
            'patient' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_of_birth' => '1960-01-01',
                'gender' => 'male',
                'ssn' => '123-45-6789',
                'phone' => '555-123-4567',
                'email' => 'john.doe@example.com',
                'address' => [
                    'line1' => '123 Main St',
                    'city' => 'Springfield',
                    'state' => 'IL',
                    'postal_code' => '62701'
                ]
            ],
            'provider' => [
                'npi' => $this->provider->npi,
                'name' => 'Dr. Jane Smith',
                'phone' => '555-987-6543'
            ],
            'facility' => [
                'name' => 'Springfield Medical Center',
                'npi' => '9876543210',
                'address' => [
                    'line1' => '456 Hospital Way',
                    'city' => 'Springfield',
                    'state' => 'IL',
                    'postal_code' => '62702'
                ]
            ],
            'insurance' => [
                'payer_name' => 'Medicare',
                'member_id' => 'MED123456789',
                'group_number' => 'GRP001',
                'policy_type' => 'primary'
            ],
            'clinical' => [
                'diagnosis_codes' => ['L89.154', 'E11.9'],
                'wound_type' => 'pressure_ulcer',
                'wound_location' => 'sacrum',
                'wound_size' => [
                    'length' => 5.2,
                    'width' => 3.8,
                    'depth' => 1.5
                ],
                'wound_stage' => 'stage_4'
            ],
            'manufacturer_id' => $this->manufacturer->id,
            'order_details' => [
                'products' => [
                    [
                        'id' => 1,
                        'quantity' => 30,
                        'size' => 'medium'
                    ]
                ],
                'shipping_method' => 'standard',
                'special_instructions' => 'Please call before delivery'
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'patient_fhir_id',
                    'practitioner_fhir_id',
                    'organization_fhir_id',
                    'manufacturer_id',
                    'status',
                    'orders' => [
                        '*' => [
                            'id',
                            'type',
                            'details'
                        ]
                    ],
                    'created_at'
                ]
            ]);

        $this->assertDatabaseHas('episodes', [
            'patient_fhir_id' => 'test-patient-123',
            'practitioner_fhir_id' => 'test-practitioner-456',
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'draft'
        ]);

        $this->assertDatabaseHas('orders', [
            'type' => 'initial'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_episode_creation()
    {
        $this->actingAs($this->provider);

        $response = $this->postJson('/api/v1/quick-request/episodes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'patient.first_name',
                'patient.last_name',
                'patient.date_of_birth',
                'provider.npi',
                'insurance.payer_name',
                'insurance.member_id',
                'clinical.diagnosis_codes',
                'manufacturer_id',
                'order_details.products'
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_active_episodes()
    {
        $this->actingAs($this->provider);

        // Create existing active episode
        $existingEpisode = Episode::factory()->create([
            'patient_fhir_id' => 'test-patient-123',
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'active'
        ]);

        $response = $this->postJson('/api/v1/quick-request/episodes', [
            'patient' => [
                'fhir_id' => 'test-patient-123' // Existing patient
            ],
            'manufacturer_id' => $this->manufacturer->id,
            // ... other required fields
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'An active episode already exists for this patient and manufacturer combination.'
            ]);
    }

    /** @test */
    public function it_adds_follow_up_order_to_existing_episode()
    {
        $this->actingAs($this->provider);

        $episode = Episode::factory()->create([
            'status' => 'active',
            'practitioner_fhir_id' => 'test-practitioner-456'
        ]);

        $response = $this->postJson("/api/v1/quick-request/episodes/{$episode->id}/orders", [
            'order_details' => [
                'products' => [
                    [
                        'id' => 2,
                        'quantity' => 60,
                        'size' => 'large'
                    ]
                ],
                'reason' => 'Monthly resupply',
                'shipping_method' => 'expedited'
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'episode_id',
                    'type',
                    'based_on',
                    'details',
                    'created_at'
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'episode_id' => $episode->id,
            'type' => 'follow_up'
        ]);
    }

    /** @test */
    public function it_approves_episode_and_sends_manufacturer_notification()
    {
        $this->actingAs($this->provider);

        $episode = Episode::factory()->create([
            'status' => 'pending_review',
            'task_fhir_id' => 'test-task-004'
        ]);

        // Mock mail sending
        \Mail::fake();

        $response = $this->postJson("/api/v1/quick-request/episodes/{$episode->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Episode approved successfully'
            ]);

        $this->assertDatabaseHas('episodes', [
            'id' => $episode->id,
            'status' => 'manufacturer_review',
            'reviewed_by' => $this->provider->id
        ]);

        \Mail::assertSent(\App\Mail\ManufacturerApprovalMail::class);
    }

    /** @test */
    public function it_enforces_permission_for_episode_approval()
    {
        $unauthorizedUser = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($unauthorizedUser);

        $episode = Episode::factory()->create(['status' => 'pending_review']);

        $response = $this->postJson("/api/v1/quick-request/episodes/{$episode->id}/approve");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_handles_fhir_service_failures_gracefully()
    {
        $this->actingAs($this->provider);

        // Mock FHIR failure
        Http::fake([
            '*/Patient' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $response = $this->postJson('/api/v1/quick-request/episodes', [
            // ... valid request data
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'message' => 'Unable to create patient record in FHIR. Please try again later.'
            ]);

        // Ensure no partial data was saved
        $this->assertDatabaseCount('episodes', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function it_validates_medicare_coverage_for_products()
    {
        $this->actingAs($this->provider);

        // Mock Medicare validation service
        $this->mock(\App\Services\MacValidationService::class)
            ->shouldReceive('validateProductCoverage')
            ->andReturn([
                'is_covered' => false,
                'reason' => 'Product not covered for diagnosis code L89.154'
            ]);

        $response = $this->postJson('/api/v1/quick-request/episodes', [
            // ... request data with non-covered product
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'order_details.products.0' => [
                        'This product is not covered by Medicare for the provided diagnosis.'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_tracks_phi_access_in_audit_log()
    {
        $this->actingAs($this->provider);

        $episode = Episode::factory()->create();

        $response = $this->getJson("/api/v1/quick-request/episodes/{$episode->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->provider->id,
            'event' => 'episode.viewed',
            'auditable_type' => Episode::class,
            'auditable_id' => $episode->id
        ]);
    }

    /** @test */
    public function it_generates_docuseal_document_for_episode()
    {
        $this->actingAs($this->provider);

        $episode = Episode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id
        ]);

        // Mock DocuSeal service
        $this->mock(\App\Services\DocusealService::class)
            ->shouldReceive('generateDocument')
            ->andReturn([
                'submission_id' => 'sub_123456',
                'url' => 'https://docuseal.co/submissions/sub_123456'
            ]);

        $response = $this->postJson("/api/v1/quick-request/episodes/{$episode->id}/generate-ivr");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'submission_id',
                    'url'
                ]
            ]);

        $this->assertDatabaseHas('docu_seal_documents', [
            'documentable_type' => Episode::class,
            'documentable_id' => $episode->id,
            'submission_id' => 'sub_123456'
        ]);
    }
}

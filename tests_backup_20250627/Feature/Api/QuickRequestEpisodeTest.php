<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Episode;
use App\Models\Users\Organization\Organization;
use App\Models\Fhir\Facility;
use App\Models\Order\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\QuickRequest\ProcessEpisodeCreation;
use Tests\TestCase;

class QuickRequestEpisodeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Facility $facility;
    private Manufacturer $manufacturer;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->organization = Organization::factory()->create();
        $this->facility = Facility::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->manufacturer = Manufacturer::factory()->create();
        
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        
        $this->user->facilities()->attach($this->facility);
    }

    public function test_creates_episode_successfully(): void
    {
        $requestData = $this->getValidEpisodeData();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/quick-request/episodes', $requestData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                    'patient_display',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('episodes', [
            'status' => 'processing',
            'manufacturer_id' => $this->manufacturer->id,
        ]);

        Queue::assertPushed(ProcessEpisodeCreation::class);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/quick-request/episodes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'patientInsurance',
                'clinicalBilling',
                'productSelection',
                'docuSealIVR',
            ]);
    }

    public function test_validates_patient_data(): void
    {
        $requestData = $this->getValidEpisodeData();
        unset($requestData['patientInsurance']['patient']['firstName']);
        unset($requestData['patientInsurance']['patient']['lastName']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/quick-request/episodes', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'patientInsurance.patient.firstName',
                'patientInsurance.patient.lastName',
            ]);
    }

    public function test_validates_insurance_data(): void
    {
        $requestData = $this->getValidEpisodeData();
        $requestData['patientInsurance']['insurance']['primary']['policyNumber'] = '';

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/quick-request/episodes', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'patientInsurance.insurance.primary.policyNumber',
            ]);
    }

    public function test_validates_clinical_data(): void
    {
        $requestData = $this->getValidEpisodeData();
        $requestData['clinicalBilling']['provider']['npi'] = '123'; // Invalid NPI

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/quick-request/episodes', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'clinicalBilling.provider.npi',
            ]);
    }

    public function test_validates_product_selection(): void
    {
        $requestData = $this->getValidEpisodeData();
        $requestData['productSelection']['products'] = []; // No products selected

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/quick-request/episodes', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'productSelection.products',
            ]);
    }

    public function test_get_episode_details(): void
    {
        $episode = Episode::factory()->create([
            'created_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/quick-request/episodes/{$episode->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'patient_fhir_id',
                    'status',
                    'patient_display',
                    'orders',
                    'tasks',
                    'documents',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_list_episodes_with_pagination(): void
    {
        Episode::factory()->count(15)->create([
            'created_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/quick-request/episodes?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'status', 'patient_display'],
                ],
                'pagination' => [
                    'total',
                    'count',
                    'per_page',
                    'current_page',
                    'total_pages',
                ],
            ])
            ->assertJsonCount(10, 'data');
    }

    public function test_filter_episodes_by_status(): void
    {
        Episode::factory()->count(3)->create([
            'created_by' => $this->user->id,
            'organization_id' => $this->organization->id,
            'status' => 'draft',
        ]);

        Episode::factory()->count(2)->create([
            'created_by' => $this->user->id,
            'organization_id' => $this->organization->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/quick-request/episodes?filters[status]=draft');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_approve_episode(): void
    {
        $episode = Episode::factory()->create([
            'created_by' => $this->user->id,
            'organization_id' => $this->organization->id,
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/quick-request/episodes/{$episode->id}/approve", [
                'notes' => 'Approved for processing',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $episode->id,
                    'status' => 'approved',
                ],
            ]);

        $this->assertDatabaseHas('episodes', [
            'id' => $episode->id,
            'status' => 'approved',
        ]);
    }

    public function test_cancel_episode(): void
    {
        $episode = Episode::factory()->create([
            'created_by' => $this->user->id,
            'organization_id' => $this->organization->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/quick-request/episodes/{$episode->id}", [
                'reason' => 'Patient cancelled request',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('episodes', [
            'id' => $episode->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_unauthorized_user_cannot_access_episodes(): void
    {
        /** @var User $otherUser */
        $otherUser = User::factory()->createOne();
        $episode = Episode::factory()->create([
            'created_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/quick-request/episodes/{$episode->id}");

        $response->assertStatus(403);
    }

    public function test_save_progress_endpoint(): void
    {
        $sessionId = 'test-session-123';
        $stepData = [
            'patient' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/quick-request/save-progress', [
                'sessionId' => $sessionId,
                'step' => 'patient-insurance',
                'data' => $stepData,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_load_progress_endpoint(): void
    {
        $sessionId = 'test-session-123';
        
        // First save some progress
        $this->actingAs($this->user)
            ->postJson('/api/v1/quick-request/save-progress', [
                'sessionId' => $sessionId,
                'step' => 'patient-insurance',
                'data' => ['test' => 'data'],
            ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/quick-request/load-progress/{$sessionId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'currentStep',
                    'data',
                    'metadata',
                ],
            ]);
    }

    private function getValidEpisodeData(): array
    {
        return [
            'patientInsurance' => [
                'patient' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'dateOfBirth' => '1990-01-01',
                    'gender' => 'male',
                    'address' => [
                        'use' => 'home',
                        'type' => 'physical',
                        'line' => ['123 Main St'],
                        'city' => 'Anytown',
                        'state' => 'NY',
                        'postalCode' => '12345',
                    ],
                    'phone' => '555-123-4567',
                ],
                'insurance' => [
                    'primary' => [
                        'type' => 'medicare',
                        'policyNumber' => 'MED123456',
                        'subscriberId' => 'SUB123456',
                        'subscriberName' => 'John Doe',
                        'subscriberRelationship' => 'self',
                        'effectiveDate' => '2024-01-01',
                        'payorName' => 'Medicare',
                    ],
                ],
            ],
            'clinicalBilling' => [
                'provider' => [
                    'npi' => '1234567890',
                    'name' => 'Dr. Smith',
                ],
                'facility' => [
                    'id' => $this->facility->id,
                    'name' => $this->facility->name,
                ],
                'diagnosis' => [
                    'primary' => [
                        'code' => 'L89.0',
                        'system' => 'icd10',
                        'display' => 'Pressure ulcer',
                    ],
                    'secondary' => [],
                ],
                'woundDetails' => [
                    'woundType' => 'pressure_ulcer',
                    'woundLocation' => 'sacrum',
                    'woundSize' => [
                        'length' => 5,
                        'width' => 3,
                        'unit' => 'cm',
                    ],
                ],
            ],
            'productSelection' => [
                'manufacturer' => [
                    'id' => $this->manufacturer->id,
                    'name' => $this->manufacturer->name,
                    'code' => $this->manufacturer->code,
                ],
                'products' => [
                    [
                        'id' => 'prod-123',
                        'name' => 'Wound Dressing',
                        'code' => 'WD-001',
                        'category' => 'dressing',
                        'quantity' => 1,
                        'frequency' => 'weekly',
                        'sizes' => [
                            ['size' => '4x4', 'quantity' => 10, 'unit' => 'each'],
                        ],
                    ],
                ],
                'deliveryPreferences' => [
                    'method' => 'standard',
                ],
            ],
            'docuSealIVR' => [
                'template' => [
                    'id' => 'template-123',
                    'name' => 'Insurance Verification',
                    'manufacturer' => $this->manufacturer->code,
                    'type' => 'insurance_verification',
                ],
                'fields' => [
                    'patientName' => 'John Doe',
                    'dateOfService' => '2024-01-15',
                ],
                'signatures' => [
                    'patient' => [
                        'signedAt' => '2024-01-15T10:00:00Z',
                        'signedBy' => 'John Doe',
                    ],
                ],
                'documents' => [
                    [
                        'id' => 'doc-123',
                        'type' => 'insurance_verification',
                        'fileName' => 'insurance_verification.pdf',
                    ],
                ],
            ],
        ];
    }
}

<?php

namespace Tests\Feature\DocuSeal;

use App\Models\User;
use App\Models\Episode;
use App\Models\Patient;
use App\Models\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class DocuSealApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Mock DocuSeal API responses
        Http::fake([
            'api.docuseal.co/*' => Http::response(['id' => 'test-submission', 'status' => 'pending'], 201)
        ]);
    }

    /** @test */
    public function it_creates_docuseal_submission_via_api()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        $response = $this->postJson('/api/v1/docuseal/submission/create', [
            'episode_id' => $episode->id,
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'submission',
                'ivr_episode',
                'mapping'
            ])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_validates_required_parameters_for_submission()
    {
        $response = $this->postJson('/api/v1/docuseal/submission/create', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['episode_id', 'manufacturer']);
    }

    /** @test */
    public function it_gets_submission_details()
    {
        $submissionId = 'test-submission-123';

        Http::fake([
            "api.docuseal.co/submissions/{$submissionId}" => Http::response([
                'id' => $submissionId,
                'status' => 'completed',
                'created_at' => '2023-01-01T00:00:00Z'
            ], 200)
        ]);

        $response = $this->getJson("/api/v1/docuseal/submission/{$submissionId}");

        $response->assertStatus(200)
            ->assertJsonStructure(['submission'])
            ->assertJsonPath('submission.id', $submissionId);
    }

    /** @test */
    public function it_sends_submission_for_signing()
    {
        $submissionId = 'test-submission-123';

        Http::fake([
            "api.docuseal.co/submissions/{$submissionId}/send" => Http::response([
                'id' => $submissionId,
                'status' => 'sent'
            ], 200)
        ]);

        $response = $this->postJson("/api/v1/docuseal/submission/{$submissionId}/send", [
            'signers' => [
                [
                    'email' => 'provider@example.com',
                    'name' => 'Dr. Smith',
                    'role' => 'provider'
                ]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('id', $submissionId)
            ->assertJsonPath('status', 'sent');
    }

    /** @test */
    public function it_validates_signers_for_sending()
    {
        $submissionId = 'test-submission-123';

        $response = $this->postJson("/api/v1/docuseal/submission/{$submissionId}/send", [
            'signers' => [
                ['email' => 'invalid-email'] // Missing required fields
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['signers.0.email']);
    }

    /** @test */
    public function it_downloads_signed_document()
    {
        $submissionId = 'test-submission-123';
        $documentContent = 'PDF document content';

        Http::fake([
            "api.docuseal.co/submissions/{$submissionId}/documents/combined/pdf" => Http::response($documentContent, 200)
        ]);

        $response = $this->getJson("/api/v1/docuseal/submission/{$submissionId}/download");

        $response->assertStatus(200);
        $this->assertEquals($documentContent, $response->getContent());
    }

    /** @test */
    public function it_gets_template_fields_for_manufacturer()
    {
        $manufacturer = 'ACZ';

        Http::fake([
            'api.docuseal.co/templates/*' => Http::response([
                'id' => 'template-123',
                'name' => 'ACZ Template',
                'fields' => [
                    ['name' => 'patient_first_name', 'type' => 'text'],
                    ['name' => 'patient_last_name', 'type' => 'text']
                ]
            ], 200)
        ]);

        $response = $this->getJson("/api/v1/docuseal/template/{$manufacturer}/fields");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'template',
                'fields',
                'mapped_count',
                'manufacturer'
            ]);
    }

    /** @test */
    public function it_batch_processes_episodes()
    {
        $episodes = Episode::factory()->count(3)->create();
        $episodeIds = $episodes->pluck('id')->toArray();

        // Create product requests
        foreach ($episodes as $episode) {
            ProductRequest::factory()->create([
                'episode_id' => $episode->id,
                'status' => 'approved'
            ]);
        }

        $response = $this->postJson('/api/v1/docuseal/batch-process', [
            'episode_ids' => $episodeIds,
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'results',
                'summary' => [
                    'total',
                    'successful',
                    'failed'
                ]
            ])
            ->assertJsonPath('summary.total', 3);
    }

    /** @test */
    public function it_gets_episodes_by_status()
    {
        // Create test episodes with different statuses
        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'completed',
            'manufacturer_name' => 'ACZ'
        ]);
        
        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'pending',
            'manufacturer_name' => 'ACZ'
        ]);

        $response = $this->getJson('/api/v1/docuseal/episodes/status/completed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'episodes',
                'status',
                'manufacturer'
            ])
            ->assertJsonPath('status', 'completed');
    }

    /** @test */
    public function it_filters_episodes_by_manufacturer()
    {
        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'completed',
            'manufacturer_name' => 'ACZ'
        ]);
        
        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'completed',
            'manufacturer_name' => 'Advanced Health'
        ]);

        $response = $this->getJson('/api/v1/docuseal/episodes/status/completed?manufacturer=ACZ');

        $response->assertStatus(200)
            ->assertJsonPath('manufacturer', 'ACZ');
    }

    /** @test */
    public function it_gets_docuseal_analytics()
    {
        // Create test data
        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'completed',
            'manufacturer_name' => 'ACZ',
            'field_mapping_completeness' => 85.0
        ]);

        $response = $this->getJson('/api/v1/docuseal/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_submissions',
                'status_breakdown',
                'completion_rate',
                'average_field_completeness'
            ]);
    }

    /** @test */
    public function it_filters_analytics_by_manufacturer_and_date()
    {
        $startDate = now()->subDays(30)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->getJson("/api/v1/docuseal/analytics?manufacturer=ACZ&start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonPath('manufacturer', 'ACZ')
            ->assertJsonPath('date_range.0', $startDate)
            ->assertJsonPath('date_range.1', $endDate);
    }

    /** @test */
    public function it_handles_docuseal_api_errors_gracefully()
    {
        // Mock API error
        Http::fake([
            'api.docuseal.co/*' => Http::response(['error' => 'API Error'], 500)
        ]);

        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        $response = $this->postJson('/api/v1/docuseal/submission/create', [
            'episode_id' => $episode->id,
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(500)
            ->assertJsonStructure(['error', 'message']);
    }

    /** @test */
    public function it_requires_authentication_for_all_endpoints()
    {
        // Clear authentication
        $this->app['auth']->forgetGuards();

        $endpoints = [
            ['POST', '/api/v1/docuseal/submission/create', ['episode_id' => 1, 'manufacturer' => 'ACZ']],
            ['GET', '/api/v1/docuseal/submission/test-123', []],
            ['POST', '/api/v1/docuseal/submission/test-123/send', ['signers' => []]],
            ['GET', '/api/v1/docuseal/template/ACZ/fields', []],
            ['POST', '/api/v1/docuseal/batch-process', ['episode_ids' => [1], 'manufacturer' => 'ACZ']],
            ['GET', '/api/v1/docuseal/episodes/status/completed', []],
            ['GET', '/api/v1/docuseal/analytics', []]
        ];

        foreach ($endpoints as [$method, $url, $data]) {
            $response = $this->json($method, $url, $data);
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function it_creates_ivr_episode_record_on_submission()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        $response = $this->postJson('/api/v1/docuseal/submission/create', [
            'episode_id' => $episode->id,
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(200);

        // Check that IVR episode was created
        $this->assertDatabaseHas('patient_manufacturer_ivr_episodes', [
            'episode_id' => $episode->id,
            'manufacturer_name' => 'ACZ',
            'docuseal_status' => 'pending'
        ]);
    }

    /** @test */
    public function it_updates_existing_ivr_episode_on_resubmission()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        // Create existing IVR episode
        $ivrEpisode = PatientManufacturerIVREpisode::factory()->create([
            'episode_id' => $episode->id,
            'manufacturer_name' => 'ACZ',
            'docuseal_submission_id' => 'existing-submission'
        ]);

        Http::fake([
            'api.docuseal.co/submissions/existing-submission' => Http::response([
                'id' => 'existing-submission',
                'status' => 'updated'
            ], 200)
        ]);

        $response = $this->postJson('/api/v1/docuseal/submission/create', [
            'episode_id' => $episode->id,
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('submission.id', 'existing-submission');
    }

    /** @test */
    public function it_validates_episode_ids_array_in_batch_process()
    {
        $response = $this->postJson('/api/v1/docuseal/batch-process', [
            'episode_ids' => 'not-an-array',
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['episode_ids']);
    }

    /** @test */
    public function it_handles_additional_data_in_submission_creation()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        $response = $this->postJson('/api/v1/docuseal/submission/create', [
            'episode_id' => $episode->id,
            'manufacturer' => 'ACZ',
            'additional_data' => [
                'custom_field' => 'custom_value',
                'override_field' => 'override_value'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'submission', 'mapping']);
    }
}
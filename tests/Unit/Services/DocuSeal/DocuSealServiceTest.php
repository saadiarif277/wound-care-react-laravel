<?php

namespace Tests\Unit\Services\DocuSeal;

use App\Services\DocuSealService;
use App\Services\UnifiedFieldMappingService;
use App\Models\PatientManufacturerIVREpisode;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class DocuSealServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocuSealService $service;
    private UnifiedFieldMappingService $fieldMappingService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fieldMappingService = $this->createMock(UnifiedFieldMappingService::class);
        $this->service = new DocuSealService($this->fieldMappingService);

        // Set up config
        Config::set('services.docuseal.api_key', 'test-api-key');
        Config::set('services.docuseal.api_url', 'https://api.docuseal.co');
    }

    /** @test */
    public function it_creates_new_submission_successfully()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        // Mock field mapping service
        $mappingResult = [
            'data' => [
                'patient_first_name' => 'John',
                'patient_last_name' => 'Doe',
                'provider_npi' => '1234567890'
            ],
            'validation' => [
                'valid' => true,
                'errors' => [],
                'warnings' => []
            ],
            'manufacturer' => [
                'id' => 1,
                'name' => 'ACZ',
                'template_id' => 'template-123',
                'signature_required' => true
            ],
            'completeness' => [
                'percentage' => 85.5
            ]
        ];

        $this->fieldMappingService->method('mapEpisodeToTemplate')
            ->with($episodeId, $manufacturer, [])
            ->willReturn($mappingResult);

        // Mock HTTP response for DocuSeal API
        Http::fake([
            'api.docuseal.co/submissions' => Http::response([
                'id' => 'submission-123',
                'status' => 'pending',
                'template_id' => 'template-123'
            ], 201)
        ]);

        $result = $this->service->createOrUpdateSubmission($episodeId, $manufacturer);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('submission', $result);
        $this->assertArrayHasKey('ivr_episode', $result);
        $this->assertArrayHasKey('mapping', $result);
        $this->assertEquals('submission-123', $result['submission']['id']);
    }

    /** @test */
    public function it_updates_existing_submission()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        // Create existing IVR episode
        $ivrEpisode = PatientManufacturerIVREpisode::factory()->create([
            'episode_id' => $episodeId,
            'manufacturer_id' => 1,
            'manufacturer_name' => $manufacturer,
            'docuseal_submission_id' => 'existing-submission-123'
        ]);

        // Mock field mapping service
        $mappingResult = [
            'data' => ['patient_first_name' => 'John'],
            'validation' => ['valid' => true, 'errors' => [], 'warnings' => []],
            'manufacturer' => [
                'id' => 1,
                'name' => $manufacturer,
                'template_id' => 'template-123'
            ],
            'completeness' => ['percentage' => 90.0]
        ];

        $this->fieldMappingService->method('mapEpisodeToTemplate')
            ->willReturn($mappingResult);

        // Mock HTTP response for update
        Http::fake([
            'api.docuseal.co/submissions/existing-submission-123' => Http::response([
                'id' => 'existing-submission-123',
                'status' => 'updated'
            ], 200)
        ]);

        $result = $this->service->createOrUpdateSubmission($episodeId, $manufacturer);

        $this->assertTrue($result['success']);
        $this->assertEquals('existing-submission-123', $result['submission']['id']);
    }

    /** @test */
    public function it_handles_validation_failure()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        // Mock field mapping with validation errors
        $mappingResult = [
            'data' => ['patient_first_name' => ''],
            'validation' => [
                'valid' => false,
                'errors' => ['Required field patient_first_name is missing'],
                'warnings' => []
            ],
            'manufacturer' => ['id' => 1, 'name' => $manufacturer],
            'completeness' => ['percentage' => 50.0]
        ];

        $this->fieldMappingService->method('mapEpisodeToTemplate')
            ->willReturn($mappingResult);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Field mapping validation failed');

        $this->service->createOrUpdateSubmission($episodeId, $manufacturer);
    }

    /** @test */
    public function it_gets_submission_details()
    {
        $submissionId = 'submission-123';

        Http::fake([
            "api.docuseal.co/submissions/{$submissionId}" => Http::response([
                'id' => $submissionId,
                'status' => 'completed',
                'template_id' => 'template-123',
                'created_at' => '2023-01-01T00:00:00Z'
            ], 200)
        ]);

        $result = $this->service->getSubmission($submissionId);

        $this->assertEquals($submissionId, $result['id']);
        $this->assertEquals('completed', $result['status']);
    }

    /** @test */
    public function it_downloads_document()
    {
        $submissionId = 'submission-123';
        $documentContent = 'PDF document content';

        Http::fake([
            "api.docuseal.co/submissions/{$submissionId}/documents/combined/pdf" => Http::response($documentContent, 200)
        ]);

        $result = $this->service->downloadDocument($submissionId);

        $this->assertEquals($documentContent, $result);
    }

    /** @test */
    public function it_sends_for_signing()
    {
        $submissionId = 'submission-123';
        $signers = [
            [
                'email' => 'provider@example.com',
                'name' => 'Dr. Smith',
                'role' => 'provider'
            ]
        ];

        Http::fake([
            "api.docuseal.co/submissions/{$submissionId}/send" => Http::response([
                'id' => $submissionId,
                'status' => 'sent',
                'sent_at' => '2023-01-01T00:00:00Z'
            ], 200)
        ]);

        $result = $this->service->sendForSigning($submissionId, $signers);

        $this->assertEquals($submissionId, $result['id']);
        $this->assertEquals('sent', $result['status']);
    }

    /** @test */
    public function it_gets_template_fields()
    {
        $manufacturer = 'ACZ';

        // Mock field mapping service to return manufacturer config
        $this->fieldMappingService->method('getManufacturerConfig')
            ->with($manufacturer)
            ->willReturn([
                'id' => 1,
                'name' => $manufacturer,
                'template_id' => 'template-123',
                'fields' => [
                    'patient_first_name' => ['required' => true],
                    'patient_last_name' => ['required' => true]
                ]
            ]);

        Http::fake([
            'api.docuseal.co/templates/template-123' => Http::response([
                'id' => 'template-123',
                'name' => 'ACZ Template',
                'fields' => [
                    ['name' => 'patient_first_name', 'type' => 'text'],
                    ['name' => 'patient_last_name', 'type' => 'text']
                ]
            ], 200)
        ]);

        $result = $this->service->getTemplateFields($manufacturer);

        $this->assertArrayHasKey('template', $result);
        $this->assertArrayHasKey('fields', $result);
        $this->assertArrayHasKey('mapped_count', $result);
        $this->assertEquals(2, $result['mapped_count']);
    }

    /** @test */
    public function it_processes_webhook_completion()
    {
        $submissionId = 'submission-123';
        
        // Create IVR episode
        $ivrEpisode = PatientManufacturerIVREpisode::factory()->create([
            'docuseal_submission_id' => $submissionId,
            'docuseal_status' => 'pending'
        ]);

        $payload = [
            'event_type' => 'submission.completed',
            'data' => [
                'id' => $submissionId,
                'documents' => [
                    ['url' => 'https://example.com/document.pdf']
                ]
            ]
        ];

        $result = $this->service->processWebhook($payload);

        $this->assertEquals('processed', $result['status']);
        $this->assertEquals('submission.completed', $result['event']);
        $this->assertEquals($ivrEpisode->id, $result['ivr_episode_id']);

        // Check that episode was updated
        $ivrEpisode->refresh();
        $this->assertEquals('completed', $ivrEpisode->docuseal_status);
        $this->assertNotNull($ivrEpisode->completed_at);
        $this->assertEquals('https://example.com/document.pdf', $ivrEpisode->signed_document_url);
    }

    /** @test */
    public function it_handles_unknown_submission_webhook()
    {
        $payload = [
            'event_type' => 'submission.completed',
            'data' => [
                'id' => 'unknown-submission-123'
            ]
        ];

        $result = $this->service->processWebhook($payload);

        $this->assertEquals('ignored', $result['status']);
        $this->assertEquals('Unknown submission', $result['reason']);
    }

    /** @test */
    public function it_batch_processes_episodes()
    {
        $episodeIds = [123, 124, 125];
        $manufacturer = 'ACZ';

        // Mock successful mapping for first two, failure for third
        $this->fieldMappingService->method('mapEpisodeToTemplate')
            ->willReturnOnConsecutiveCalls(
                ['data' => [], 'validation' => ['valid' => true], 'manufacturer' => ['id' => 1, 'template_id' => 'template-123'], 'completeness' => ['percentage' => 80]],
                ['data' => [], 'validation' => ['valid' => true], 'manufacturer' => ['id' => 1, 'template_id' => 'template-123'], 'completeness' => ['percentage' => 85]],
                $this->throwException(new \Exception('Episode not found'))
            );

        Http::fake([
            'api.docuseal.co/submissions' => Http::response(['id' => 'submission-123'], 201)
        ]);

        $result = $this->service->batchProcessEpisodes($episodeIds, $manufacturer);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertEquals(3, $result['summary']['total']);
        $this->assertEquals(2, $result['summary']['successful']);
        $this->assertEquals(1, $result['summary']['failed']);
    }

    /** @test */
    public function it_gets_episodes_by_status()
    {
        // Create test episodes
        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'completed',
            'manufacturer_name' => 'ACZ'
        ]);
        
        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'pending',
            'manufacturer_name' => 'ACZ'
        ]);
        
        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'completed',
            'manufacturer_name' => 'Advanced Health'
        ]);

        $episodes = $this->service->getEpisodesByStatus('completed', 'ACZ');

        $this->assertCount(1, $episodes);
        $this->assertEquals('completed', $episodes->first()->docuseal_status);
        $this->assertEquals('ACZ', $episodes->first()->manufacturer_name);
    }

    /** @test */
    public function it_generates_analytics()
    {
        // Create test data
        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'completed',
            'manufacturer_name' => 'ACZ',
            'field_mapping_completeness' => 85.5,
            'required_fields_completeness' => 90.0,
            'created_at' => now()->subMinutes(30),
            'completed_at' => now()
        ]);

        PatientManufacturerIVREpisode::factory()->create([
            'docuseal_status' => 'pending',
            'manufacturer_name' => 'ACZ',
            'field_mapping_completeness' => 75.0,
            'required_fields_completeness' => 80.0
        ]);

        $analytics = $this->service->generateAnalytics('ACZ');

        $this->assertEquals(2, $analytics['total_submissions']);
        $this->assertEquals(1, $analytics['status_breakdown']['completed']);
        $this->assertEquals(1, $analytics['status_breakdown']['pending']);
        $this->assertEquals(50.0, $analytics['completion_rate']); // 1 completed out of 2
        $this->assertEquals(80.25, $analytics['average_field_completeness']); // (85.5 + 75) / 2
        $this->assertEquals(30, $analytics['average_time_to_complete_minutes']);
    }

    /** @test */
    public function it_handles_docuseal_api_errors()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        $this->fieldMappingService->method('mapEpisodeToTemplate')
            ->willReturn([
                'data' => ['patient_first_name' => 'John'],
                'validation' => ['valid' => true, 'errors' => [], 'warnings' => []],
                'manufacturer' => ['id' => 1, 'template_id' => 'template-123'],
                'completeness' => ['percentage' => 80.0]
            ]);

        // Mock API error
        Http::fake([
            'api.docuseal.co/submissions' => Http::response(['error' => 'API Error'], 500)
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create DocuSeal submission');

        $this->service->createOrUpdateSubmission($episodeId, $manufacturer);
    }
}
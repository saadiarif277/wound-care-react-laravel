<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\QuickRequestService;
use App\Services\HealthData\Clients\AzureFhirClient;
use App\Services\DocuSealService;
use App\Services\Templates\DocuSealBuilder;
use App\Mail\ManufacturerOrderEmail;
use App\Models\Episode;
use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use App\Models\DocuSeal\DocuSealTemplate;

class QuickRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private $mockFhirClient;
    private $mockDocuSealService;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockFhirClient = \Mockery::mock(AzureFhirClient::class);
        $this->mockDocuSealService = \Mockery::mock(DocuSealService::class);
        
        $this->service = new QuickRequestService(
            $this->mockFhirClient,
            $this->mockDocuSealService
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_start_episode_creates_episode_and_initial_order_and_generates_docuseal()
    {
        $this->mockFhirClient->shouldReceive('createBundle')->once()->andReturn([]);

        $this->mockDocuSealService->shouldReceive('createIVRSubmission')
            ->once()
            ->andReturn(['embed_url' => 'http://example.com/ivrs', 'submission_id' => 'sub1']);

        $data = [
            'patient_fhir_id'    => 'pfhir',
            'patient_display_id' => 'disp',
            'manufacturer_id'    => Str::uuid()->toString(),
            'order_details'      => ['foo' => 'bar'],
        ];

        $episode = $this->service->startEpisode($data);
        $this->assertDatabaseHas('patient_manufacturer_ivr_episodes', ['id' => $episode->id]);
        $this->assertEquals('draft', $episode->status);

        $order = $episode->orders()->first();
        $this->assertNotNull($order);
        $this->assertEquals('initial', $order->type);
        $this->assertEquals($data['order_details'], $order->details);

        $episode->refresh();
        $this->assertEquals('http://example.com/ivrs', $episode->docuseal_submission_url);
    }

    public function test_start_episode_with_complete_fhir_orchestration()
    {
        // Arrange
        $manufacturer = Manufacturer::factory()->create();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);
        
        $requestData = [
            'patient' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_of_birth' => '1980-01-01',
                'gender' => 'male',
                'phone' => '555-0123',
                'email' => 'john.doe@example.com',
                'address_line1' => '123 Main St',
                'city' => 'Springfield',
                'state' => 'IL',
                'zip' => '62701',
                'member_id' => 'MEM123'
            ],
            'provider' => [
                'name' => 'Dr. Smith',
                'npi' => '1234567890',
                'email' => 'dr.smith@example.com',
                'credentials' => 'MD'
            ],
            'facility' => [
                'name' => 'Springfield Medical Center',
                'address' => '456 Hospital Way',
                'city' => 'Springfield',
                'state' => 'IL',
                'zip' => '62701',
                'phone' => '555-0456',
                'npi' => '0987654321'
            ],
            'clinical' => [
                'diagnosis_code' => 'L97.1',
                'diagnosis_description' => 'Non-pressure chronic ulcer of thigh',
                'wound_type' => 'venous_ulcer',
                'wound_location' => 'left_thigh',
                'wound_length' => 5.5,
                'wound_width' => 3.2,
                'wound_depth' => 0.8,
                'onset_date' => '2024-01-01',
                'clinical_notes' => 'Venous ulcer with moderate drainage'
            ],
            'insurance' => [
                'payer_name' => 'Medicare',
                'member_id' => 'MEM123',
                'type' => 'HMO',
                'group_number' => 'GRP456'
            ],
            'product' => [
                'id' => $product->id,
                'code' => 'A6234',
                'name' => 'Hydrocolloid Dressing',
                'quantity' => 10,
                'size' => '4x4'
            ],
            'manufacturer_id' => $manufacturer->id,
            'order_details' => [
                'product_id' => $product->id,
                'quantity' => 10,
                'size' => '4x4',
                'notes' => 'Weekly changes',
                'urgency' => 'routine'
            ]
        ];

        // Mock FHIR responses for all 10 resources
        $this->mockFhirClient->shouldReceive('create')
            ->with('Patient', \Mockery::any())
            ->andReturn(['id' => 'patient-123']);
            
        $this->mockFhirClient->shouldReceive('search')
            ->with('Practitioner', \Mockery::any())
            ->andReturn(['entry' => []]);
            
        $this->mockFhirClient->shouldReceive('create')
            ->with('Practitioner', \Mockery::any())
            ->andReturn(['id' => 'practitioner-123']);
            
        $this->mockFhirClient->shouldReceive('create')
            ->with('Organization', \Mockery::any())
            ->andReturn(['id' => 'org-123']);
            
        $this->mockFhirClient->shouldReceive('create')
            ->with('Condition', \Mockery::any())
            ->andReturn(['id' => 'condition-123']);
            
        $this->mockFhirClient->shouldReceive('create')
            ->with('EpisodeOfCare', \Mockery::any())
            ->andReturn(['id' => 'episode-123']);
            
        $this->mockFhirClient->shouldReceive('create')
            ->with('Coverage', \Mockery::any())
            ->andReturn(['id' => 'coverage-123']);
            
        $this->mockFhirClient->shouldReceive('create')
            ->with('Encounter', \Mockery::any())
            ->andReturn(['id' => 'encounter-123']);
            
        $this->mockFhirClient->shouldReceive('create')
            ->with('QuestionnaireResponse', \Mockery::any())
            ->andReturn(['id' => 'questionnaire-123']);
            
        $this->mockFhirClient->shouldReceive('create')
            ->with('DeviceRequest', \Mockery::any())
            ->andReturn(['id' => 'device-request-123']);
            
        $this->mockFhirClient->shouldReceive('create')
            ->with('Task', \Mockery::any())
            ->andReturn(['id' => 'task-123']);

        // Mock DocuSeal
        $this->mockDocuSealService->shouldReceive('createIVRSubmission')
            ->andReturn(['embed_url' => 'https://docuseal.com/embed/123']);

        // Act
        $episode = $this->service->startEpisode($requestData);

        // Assert
        $this->assertInstanceOf(Episode::class, $episode);
        $this->assertEquals('draft', $episode->status);
        $this->assertEquals($manufacturer->id, $episode->manufacturer_id);
        $this->assertStringStartsWith('JO', $episode->patient_display_id); // First 2 letters of John
        $this->assertStringContainsString('DO', $episode->patient_display_id); // First 2 letters of Doe
        
        // Check FHIR IDs stored
        $metadata = $episode->metadata;
        $this->assertEquals('patient-123', $metadata['fhir_ids']['patient_id']);
        $this->assertEquals('practitioner-123', $metadata['fhir_ids']['practitioner_id']);
        $this->assertEquals('org-123', $metadata['fhir_ids']['organization_id']);
        $this->assertEquals('condition-123', $metadata['fhir_ids']['condition_id']);
        $this->assertEquals('episode-123', $metadata['fhir_ids']['episode_of_care_id']);
        $this->assertEquals('coverage-123', $metadata['fhir_ids']['coverage_id']);
        $this->assertEquals('encounter-123', $metadata['fhir_ids']['encounter_id']);
        $this->assertEquals('questionnaire-123', $metadata['fhir_ids']['questionnaire_response_id']);
        $this->assertEquals('device-request-123', $metadata['fhir_ids']['device_request_id']);
        $this->assertEquals('task-123', $metadata['fhir_ids']['task_id']);
        
        // Check order created
        $this->assertEquals(1, $episode->orders()->count());
        $order = $episode->orders()->first();
        $this->assertEquals('initial', $order->type);
        $this->assertEquals($requestData['order_details'], $order->details);
    }

    public function test_start_episode_continues_when_fhir_fails()
    {
        // Arrange
        $manufacturer = Manufacturer::factory()->create();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);
        
        $requestData = [
            'patient' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'date_of_birth' => '1985-05-15',
                'gender' => 'female',
                'phone' => '555-9876',
                'email' => 'jane.smith@example.com',
                'address_line1' => '789 Oak Ave',
                'city' => 'Chicago',
                'state' => 'IL',
                'zip' => '60601',
                'member_id' => 'MEM456'
            ],
            'manufacturer_id' => $manufacturer->id,
            'order_details' => [
                'product_id' => $product->id,
                'quantity' => 5
            ]
        ];

        // Mock FHIR to throw exception
        $this->mockFhirClient->shouldReceive('create')
            ->andThrow(new \Exception('FHIR service unavailable'));

        // Mock DocuSeal to work normally
        $this->mockDocuSealService->shouldReceive('createIVRSubmission')
            ->andReturn(['embed_url' => 'https://docuseal.com/embed/456']);

        // Act
        $episode = $this->service->startEpisode($requestData);

        // Assert - Episode should still be created
        $this->assertInstanceOf(Episode::class, $episode);
        $this->assertEquals('draft', $episode->status);
        $this->assertEquals($manufacturer->id, $episode->manufacturer_id);
        $this->assertStringStartsWith('JA', $episode->patient_display_id);
        $this->assertStringContainsString('SM', $episode->patient_display_id);
    }

    public function test_add_follow_up_creates_follow_up_order()
    {
        $fhirClient = \Mockery::mock(AzureFhirClient::class);
        $fhirClient->shouldReceive('createBundle')->once()->andReturn([]);

        $docuSealService = \Mockery::mock(DocuSealService::class);
        $docuSealService->shouldNotReceive('createIVRSubmission');

        $service = new QuickRequestService($fhirClient, $docuSealService);

        $episode = Episode::create([
            'patient_fhir_id'    => 'pfhir2',
            'patient_display_id' => 'disp2',
            'manufacturer_id'    => Str::uuid()->toString(),
            'status'             => 'draft',
        ]);

        $parentOrder = Order::create([
            'episode_id' => $episode->id,
            'type'       => 'initial',
            'details'    => [],
        ]);

        $data = [
            'parent_order_id' => $parentOrder->id,
            'order_details'   => ['baz' => 'qux'],
        ];

        $order = $service->addFollowUp($episode, $data);
        $this->assertEquals('follow_up', $order->type);
        $this->assertEquals($parentOrder->id, $order->parent_order_id);
        $this->assertEquals($data['order_details'], $order->details);
    }

    public function test_approve_transitions_and_sends_email()
    {
        Mail::fake();

        $fhirClient = \Mockery::mock(AzureFhirClient::class);
        $docuSealService = \Mockery::mock(DocuSealService::class);
        $service = new QuickRequestService($fhirClient, $docuSealService);

        $manufacturerId = Str::uuid()->toString();
        // Create episode record
        $episode = Episode::create([
            'patient_fhir_id'    => 'p',
            'patient_display_id' => 'd',
            'manufacturer_id'    => $manufacturerId,
            'status'             => 'ready_for_review',
        ]);

        // Update manufacturer contact email
        $episode->manufacturer()->associate(
            \App\Models\Order\Manufacturer::create([
                'name'          => 'Test',
                'slug'          => 'test',
                'contact_email' => 'mfg@example.com',
                'contact_phone' => '123',
                'address'       => [],
                'website'       => '',
                'is_active'     => true,
            ])
        )->save();

        $service->approve($episode);

        $this->assertEquals('manufacturer_review', $episode->fresh()->status);
        Mail::assertSent(ManufacturerOrderEmail::class);
    }
}
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order\Product;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\ProductRequest;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class QuickRequestPatientDisplayIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();
        Auth::login($this->user);

        // Create test product
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'product_code' => 'TEST001',
            'manufacturer_id' => 1
        ]);
    }

    /**
     * Test that patient_display_id is generated when episode has null value
     */
    public function test_patient_display_id_is_generated_when_null()
    {
        // Create episode with null patient_display_id
        $episode = PatientManufacturerIVREpisode::create([
            'patient_id' => 'test-patient-123',
            'patient_fhir_id' => 'Patient/test-patient-123',
            'patient_display_id' => null, // Explicitly set to null
            'manufacturer_id' => 1,
            'status' => 'ready_for_review',
            'ivr_status' => 'na',
            'created_by' => $this->user->id,
            'metadata' => []
        ]);

        // Mock the orchestrator to return our episode
        $this->mock(QuickRequestOrchestrator::class, function ($mock) use ($episode) {
            $mock->shouldReceive('startEpisode')
                ->once()
                ->andReturn($episode);
        });

        // Prepare form data
        $formData = [
            'provider_id' => $this->user->id,
            'facility_id' => 1,
            'request_type' => 'quick_request',
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'patient_dob' => '1990-01-01',
            'primary_insurance_name' => 'Test Insurance',
            'primary_member_id' => 'TEST123',
            'expected_service_date' => '2024-12-20',
            'wound_type' => 'surgical',
            'place_of_service' => '11',
            'selected_products' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1
                ]
            ]
        ];

        // Submit the order
        $response = $this->postJson('/quick-requests/submit-order', [
            'formData' => $formData,
            'episodeData' => []
        ]);

        // Assert the response is successful
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Get the created product request
        $productRequest = ProductRequest::where('request_number', 'like', 'REQ-%')->first();

        // Assert that patient_display_id is not null
        $this->assertNotNull($productRequest->patient_display_id);
        $this->assertNotEmpty($productRequest->patient_display_id);

        // Assert that the episode was updated with the generated display ID
        $episode->refresh();
        $this->assertNotNull($episode->patient_display_id);
        $this->assertEquals($productRequest->patient_display_id, $episode->patient_display_id);

        // Assert the format matches our expected pattern (either name-based or PAT-based)
        $this->assertMatchesRegularExpression('/^([A-Z]{4}\d{3}|PAT\d{6})$/', $productRequest->patient_display_id);
    }

    /**
     * Test that existing patient_display_id is preserved when not null
     */
    public function test_existing_patient_display_id_is_preserved()
    {
        $existingDisplayId = 'EXIST123';

        // Create episode with existing patient_display_id
        $episode = PatientManufacturerIVREpisode::create([
            'patient_id' => 'test-patient-456',
            'patient_fhir_id' => 'Patient/test-patient-456',
            'patient_display_id' => $existingDisplayId,
            'manufacturer_id' => 1,
            'status' => 'ready_for_review',
            'ivr_status' => 'na',
            'created_by' => $this->user->id,
            'metadata' => []
        ]);

        // Mock the orchestrator to return our episode
        $this->mock(QuickRequestOrchestrator::class, function ($mock) use ($episode) {
            $mock->shouldReceive('startEpisode')
                ->once()
                ->andReturn($episode);
        });

        // Prepare form data
        $formData = [
            'provider_id' => $this->user->id,
            'facility_id' => 1,
            'request_type' => 'quick_request',
            'patient_first_name' => 'Jane',
            'patient_last_name' => 'Smith',
            'patient_dob' => '1985-05-15',
            'primary_insurance_name' => 'Test Insurance',
            'primary_member_id' => 'TEST456',
            'expected_service_date' => '2024-12-21',
            'wound_type' => 'surgical',
            'place_of_service' => '11',
            'selected_products' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1
                ]
            ]
        ];

        // Submit the order
        $response = $this->postJson('/quick-requests/submit-order', [
            'formData' => $formData,
            'episodeData' => []
        ]);

        // Assert the response is successful
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Get the created product request
        $productRequest = ProductRequest::where('request_number', 'like', 'REQ-%')->first();

        // Assert that the existing patient_display_id is preserved
        $this->assertEquals($existingDisplayId, $productRequest->patient_display_id);

        // Assert that the episode was not changed
        $episode->refresh();
        $this->assertEquals($existingDisplayId, $episode->patient_display_id);
    }
}

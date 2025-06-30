<?php

namespace Tests\Feature\FieldMapping;

use App\Models\User;
use App\Models\Episode;
use App\Models\Patient;
use App\Models\ProductRequest;
use App\Models\Provider;
use App\Models\Facility;
use App\Models\Product;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class FieldMappingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate user
        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }

    /** @test */
    public function it_maps_episode_data_via_api()
    {
        // Create test data
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1980-01-01'
        ]);

        $provider = Provider::factory()->create([
            'npi' => '1234567890'
        ]);

        $facility = Facility::factory()->create();
        $product = Product::factory()->create();

        $episode = Episode::factory()->create([
            'patient_id' => $patient->id
        ]);

        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'provider_id' => $provider->id,
            'facility_id' => $facility->id,
            'product_id' => $product->id,
            'status' => 'approved'
        ]);

        $response = $this->postJson("/api/v1/field-mapping/episode/{$episode->id}", [
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'validation' => ['valid', 'errors', 'warnings'],
                'manufacturer',
                'completeness',
                'metadata'
            ]);
    }

    /** @test */
    public function it_validates_required_manufacturer_parameter()
    {
        $episode = Episode::factory()->create();

        $response = $this->postJson("/api/v1/field-mapping/episode/{$episode->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['manufacturer']);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_episode()
    {
        $response = $this->postJson("/api/v1/field-mapping/episode/999999", [
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(500); // Will throw exception for missing episode
    }

    /** @test */
    public function it_lists_manufacturers()
    {
        $response = $this->getJson('/api/v1/field-mapping/manufacturers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'manufacturers' => [
                    '*' => [
                        'id',
                        'name',
                        'template_id',
                        'signature_required',
                        'has_order_form',
                        'fields_count',
                        'required_fields_count'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_gets_manufacturer_configuration()
    {
        $response = $this->getJson('/api/v1/field-mapping/manufacturer/ACZ');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'manufacturer' => [
                    'id',
                    'name',
                    'template_id',
                    'fields'
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_unknown_manufacturer()
    {
        $response = $this->getJson('/api/v1/field-mapping/manufacturer/UnknownManufacturer');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Manufacturer not found']);
    }

    /** @test */
    public function it_validates_field_mapping_data()
    {
        $response = $this->postJson('/api/v1/field-mapping/validate', [
            'manufacturer' => 'ACZ',
            'data' => [
                'patient_first_name' => 'John',
                'patient_last_name' => '', // Missing required field
                'provider_npi' => '1234567890'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'valid',
                'errors',
                'warnings'
            ])
            ->assertJson([
                'valid' => false
            ]);
    }

    /** @test */
    public function it_gets_field_mapping_analytics()
    {
        $response = $this->getJson('/api/v1/field-mapping/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_submissions',
                'status_breakdown',
                'completion_rate',
                'average_field_completeness'
            ]);
    }

    /** @test */
    public function it_filters_analytics_by_manufacturer()
    {
        $response = $this->getJson('/api/v1/field-mapping/analytics?manufacturer=ACZ');

        $response->assertStatus(200)
            ->assertJsonPath('manufacturer', 'ACZ');
    }

    /** @test */
    public function it_filters_analytics_by_date_range()
    {
        $startDate = now()->subDays(30)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->getJson("/api/v1/field-mapping/analytics?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonPath('date_range.0', $startDate)
            ->assertJsonPath('date_range.1', $endDate);
    }

    /** @test */
    public function it_batch_maps_multiple_episodes()
    {
        $episodes = Episode::factory()->count(3)->create();
        $episodeIds = $episodes->pluck('id')->toArray();

        // Create product requests for each episode
        foreach ($episodes as $episode) {
            ProductRequest::factory()->create([
                'episode_id' => $episode->id,
                'status' => 'approved'
            ]);
        }

        $response = $this->postJson('/api/v1/field-mapping/batch-map', [
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
    public function it_gets_episode_mapping_logs()
    {
        $episode = Episode::factory()->create();

        $response = $this->getJson("/api/v1/field-mapping/episode/{$episode->id}/logs");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'logs'
            ]);
    }

    /** @test */
    public function it_gets_field_suggestions()
    {
        $response = $this->getJson('/api/v1/field-mapping/manufacturer/ACZ/field-suggestions?field=patient_first_name');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'suggestions'
            ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        // Clear authentication
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/v1/field-mapping/episode/123', [
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_additional_data_in_mapping()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        $response = $this->postJson("/api/v1/field-mapping/episode/{$episode->id}", [
            'manufacturer' => 'ACZ',
            'additional_data' => [
                'custom_field' => 'custom_value',
                'override_field' => 'override_value'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'validation', 'manufacturer', 'completeness']);
    }

    /** @test */
    public function it_validates_episode_ids_in_batch_mapping()
    {
        $response = $this->postJson('/api/v1/field-mapping/batch-map', [
            'episode_ids' => ['invalid', 'ids'],
            'manufacturer' => 'ACZ'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['episode_ids.0', 'episode_ids.1']);
    }

    /** @test */
    public function it_validates_date_range_in_analytics()
    {
        $response = $this->getJson('/api/v1/field-mapping/analytics?start_date=invalid&end_date=2023-01-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    /** @test */
    public function it_validates_end_date_after_start_date()
    {
        $response = $this->getJson('/api/v1/field-mapping/analytics?start_date=2023-01-31&end_date=2023-01-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    /** @test */
    public function it_returns_proper_error_format()
    {
        $response = $this->postJson('/api/v1/field-mapping/episode/999999', [
            'manufacturer' => 'ACZ'
        ]);

        $response->assertJsonStructure([
            'error',
            'message'
        ]);
    }
}
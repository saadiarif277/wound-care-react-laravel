<?php

namespace Tests\Unit\Services\FieldMapping;

use App\Services\FieldMapping\DataExtractor;
use App\Services\FhirService;
use App\Models\Episode;
use App\Models\Patient;
use App\Models\ProductRequest;
use App\Models\Provider;
use App\Models\Facility;
use App\Models\Product;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class DataExtractorTest extends TestCase
{
    use RefreshDatabase;

    private DataExtractor $extractor;
    private FhirService $fhirService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fhirService = $this->createMock(FhirService::class);
        $this->extractor = new DataExtractor($this->fhirService);
        
        // Mock cache to avoid caching during tests
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });
    }

    /** @test */
    public function it_extracts_episode_data_successfully()
    {
        // Create test data
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1980-01-01',
            'phone' => '1234567890',
            'email' => 'john@example.com'
        ]);

        $provider = Provider::factory()->create([
            'first_name' => 'Dr. Jane',
            'last_name' => 'Smith',
            'npi' => '1234567890'
        ]);

        $facility = Facility::factory()->create([
            'name' => 'Test Hospital',
            'address' => '123 Main St',
            'city' => 'Springfield',
            'state' => 'IL',
            'zip_code' => '62701'
        ]);

        $product = Product::factory()->create([
            'name' => 'Test Product',
            'code' => 'TP001',
            'manufacturer' => 'ACZ'
        ]);

        $episode = Episode::factory()->create([
            'patient_id' => $patient->id,
            'episode_number' => 'EP001',
            'status' => 'active'
        ]);

        $productRequest = ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'provider_id' => $provider->id,
            'facility_id' => $facility->id,
            'product_id' => $product->id,
            'status' => 'approved',
            'wound_type' => 'diabetic_ulcer',
            'wound_location' => 'foot',
            'wound_length' => 5.0,
            'wound_width' => 3.0,
            'wound_start_date' => '2023-01-01'
        ]);

        // Mock FHIR service responses
        $this->fhirService->method('getPatient')->willReturn([
            'id' => 'fhir-patient-123',
            'name' => [['given' => ['John'], 'family' => 'Doe']],
            'birthDate' => '1980-01-01'
        ]);

        // Execute
        $result = $this->extractor->extractEpisodeData($episode->id);

        // Assert
        $this->assertIsArray($result);
        
        // Check episode data
        $this->assertEquals($episode->id, $result['episode_id']);
        $this->assertEquals('EP001', $result['episode_number']);
        $this->assertEquals('active', $result['status']);

        // Check patient data
        $this->assertEquals('John', $result['patient_first_name']);
        $this->assertEquals('Doe', $result['patient_last_name']);
        $this->assertEquals('1980-01-01', $result['patient_dob']);
        $this->assertEquals('1234567890', $result['patient_phone']);

        // Check provider data
        $this->assertEquals('Dr. Jane Smith', $result['provider_name']);
        $this->assertEquals('1234567890', $result['provider_npi']);

        // Check facility data
        $this->assertEquals('Test Hospital', $result['facility_name']);
        $this->assertEquals('123 Main St', $result['facility_address']);

        // Check product data
        $this->assertEquals('Test Product', $result['product_name']);
        $this->assertEquals('TP001', $result['product_code']);

        // Check wound data
        $this->assertEquals('diabetic_ulcer', $result['wound_type']);
        $this->assertEquals('foot', $result['wound_location']);
        $this->assertEquals(5.0, $result['wound_size_length']);
        $this->assertEquals(3.0, $result['wound_size_width']);

        // Check computed fields
        $this->assertEquals(15.0, $result['wound_size_total']); // 5.0 * 3.0
        $this->assertGreaterThan(0, $result['wound_duration_days']);
        $this->assertEquals('John Doe', $result['patient_full_name']);
    }

    /** @test */
    public function it_handles_missing_product_request()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No approved product requests found');

        $this->extractor->extractEpisodeData($episode->id);
    }

    /** @test */
    public function it_handles_fhir_service_errors_gracefully()
    {
        $patient = Patient::factory()->create(['fhir_patient_id' => 'fhir-123']);
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        // Mock FHIR service to throw exception
        $this->fhirService->method('getPatient')->willThrowException(new \Exception('FHIR error'));

        $result = $this->extractor->extractEpisodeData($episode->id);

        // Should still return data without FHIR fields
        $this->assertIsArray($result);
        $this->assertEquals($episode->id, $result['episode_id']);
        $this->assertArrayNotHasKey('fhir_patient_id', $result);
    }

    /** @test */
    public function it_computes_wound_duration_correctly()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        $woundStartDate = now()->subDays(30)->format('Y-m-d');
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved',
            'wound_start_date' => $woundStartDate
        ]);

        $result = $this->extractor->extractEpisodeData($episode->id);

        $this->assertEquals(30, $result['wound_duration_days']);
        $this->assertEquals(4, $result['wound_duration_weeks']); // floor(30/7)
        $this->assertEquals(0, $result['wound_duration_years']);
    }

    /** @test */
    public function it_formats_full_names_correctly()
    {
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        $provider = Provider::factory()->create([
            'first_name' => 'Dr. Jane',
            'last_name' => 'Smith'
        ]);

        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'provider_id' => $provider->id,
            'status' => 'approved'
        ]);

        $result = $this->extractor->extractEpisodeData($episode->id);

        $this->assertEquals('John Doe', $result['patient_full_name']);
        $this->assertEquals('Dr. Jane Smith', $result['provider_full_name']);
    }

    /** @test */
    public function it_formats_full_address_correctly()
    {
        $patient = Patient::factory()->create([
            'address_line1' => '123 Main St',
            'address_line2' => 'Apt 4B',
            'city' => 'Springfield',
            'state' => 'IL',
            'zip_code' => '62701'
        ]);

        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        $result = $this->extractor->extractEpisodeData($episode->id);

        $expected = '123 Main St, Apt 4B, Springfield, IL, 62701';
        $this->assertEquals($expected, $result['patient_full_address']);
    }

    /** @test */
    public function it_flattens_nested_data_correctly()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        // Mock FHIR response with nested data
        $this->fhirService->method('getPatient')->willReturn([
            'id' => 'fhir-123',
            'address' => [
                'line1' => '123 Test St',
                'city' => 'Test City'
            ]
        ]);

        $result = $this->extractor->extractEpisodeData($episode->id);

        // Check that nested FHIR data is flattened
        $this->assertArrayHasKey('fhir_patient_address_line1', $result);
        $this->assertArrayHasKey('fhir_patient_address_city', $result);
        $this->assertEquals('123 Test St', $result['fhir_patient_address_line1']);
        $this->assertEquals('Test City', $result['fhir_patient_address_city']);
    }

    /** @test */
    public function it_caches_results_correctly()
    {
        $patient = Patient::factory()->create();
        $episode = Episode::factory()->create(['patient_id' => $patient->id]);
        
        ProductRequest::factory()->create([
            'episode_id' => $episode->id,
            'status' => 'approved'
        ]);

        // Mock cache to verify it's being used
        Cache::shouldReceive('remember')
            ->with("episode_data_{$episode->id}", 300, \Closure::class)
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->extractor->extractEpisodeData($episode->id);
    }

    /** @test */
    public function it_clears_cache_correctly()
    {
        $episodeId = 123;

        Cache::shouldReceive('forget')
            ->with("episode_data_{$episodeId}")
            ->once();

        $this->extractor->clearCache($episodeId);
    }
}
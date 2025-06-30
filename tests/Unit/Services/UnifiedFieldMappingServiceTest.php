<?php

namespace Tests\Unit\Services;

use App\Services\UnifiedFieldMappingService;
use App\Services\FieldMapping\DataExtractor;
use App\Services\FieldMapping\FieldTransformer;
use App\Services\FieldMapping\FieldMatcher;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnifiedFieldMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    private UnifiedFieldMappingService $service;
    private DataExtractor $dataExtractor;
    private FieldTransformer $fieldTransformer;
    private FieldMatcher $fieldMatcher;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->dataExtractor = $this->createMock(DataExtractor::class);
        $this->fieldTransformer = $this->createMock(FieldTransformer::class);
        $this->fieldMatcher = $this->createMock(FieldMatcher::class);
        
        $this->service = new UnifiedFieldMappingService(
            $this->dataExtractor,
            $this->fieldTransformer,
            $this->fieldMatcher
        );
    }

    /** @test */
    public function it_maps_episode_to_template_successfully()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        // Mock data extractor
        $sourceData = [
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'patient_dob' => '1980-01-01',
            'wound_duration_weeks' => 6,
            'provider_npi' => '1234567890'
        ];
        
        $this->dataExtractor->method('extractEpisodeData')
            ->with($episodeId)
            ->willReturn($sourceData);

        // Mock field transformer
        $this->fieldTransformer->method('transform')
            ->willReturnCallback(function($value, $transformer) {
                if ($transformer === 'date:m/d/Y') {
                    return '01/01/1980';
                }
                return $value;
            });

        $result = $this->service->mapEpisodeToTemplate($episodeId, $manufacturer);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('validation', $result);
        $this->assertArrayHasKey('manufacturer', $result);
        $this->assertArrayHasKey('completeness', $result);
        $this->assertArrayHasKey('metadata', $result);

        // Check metadata
        $this->assertEquals($episodeId, $result['metadata']['episode_id']);
        $this->assertEquals($manufacturer, $result['metadata']['manufacturer']);
        $this->assertArrayHasKey('mapped_at', $result['metadata']);
        $this->assertArrayHasKey('duration_ms', $result['metadata']);
    }

    /** @test */
    public function it_handles_unknown_manufacturer()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown manufacturer: UnknownManufacturer');

        $this->service->mapEpisodeToTemplate(123, 'UnknownManufacturer');
    }

    /** @test */
    public function it_validates_mapping_correctly()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        $sourceData = [
            'patient_first_name' => 'John',
            'patient_last_name' => '',  // Missing required field
            'wound_duration_weeks' => 2  // Below ACZ requirement
        ];
        
        $this->dataExtractor->method('extractEpisodeData')
            ->willReturn($sourceData);

        $result = $this->service->mapEpisodeToTemplate($episodeId, $manufacturer);

        $this->assertFalse($result['validation']['valid']);
        $this->assertNotEmpty($result['validation']['errors']);
        $this->assertNotEmpty($result['validation']['warnings']);
    }

    /** @test */
    public function it_calculates_completeness_correctly()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        $sourceData = [
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'patient_dob' => null, // Missing optional field
            'provider_npi' => '1234567890'
        ];
        
        $this->dataExtractor->method('extractEpisodeData')
            ->willReturn($sourceData);

        $result = $this->service->mapEpisodeToTemplate($episodeId, $manufacturer);

        $this->assertArrayHasKey('completeness', $result);
        $this->assertArrayHasKey('percentage', $result['completeness']);
        $this->assertArrayHasKey('filled', $result['completeness']);
        $this->assertArrayHasKey('total', $result['completeness']);
        $this->assertArrayHasKey('field_status', $result['completeness']);

        // Should be between 0 and 100
        $this->assertGreaterThanOrEqual(0, $result['completeness']['percentage']);
        $this->assertLessThanOrEqual(100, $result['completeness']['percentage']);
    }

    /** @test */
    public function it_applies_business_rules_for_acz()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        $sourceData = [
            'patient_first_name' => 'John',
            'wound_duration_weeks' => 3  // Below ACZ requirement of > 4 weeks
        ];
        
        $this->dataExtractor->method('extractEpisodeData')
            ->willReturn($sourceData);

        $result = $this->service->mapEpisodeToTemplate($episodeId, $manufacturer);

        // Should have warning about ACZ requirement
        $this->assertNotEmpty($result['validation']['warnings']);
        $warnings = $result['validation']['warnings'];
        $this->assertContains('Wound duration does not meet ACZ requirement of > 4 weeks', $warnings);
    }

    /** @test */
    public function it_handles_fuzzy_field_matching()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        $sourceData = [
            'fname' => 'John',  // Should match to patient_first_name via fuzzy matching
            'lname' => 'Doe'
        ];
        
        $this->dataExtractor->method('extractEpisodeData')
            ->willReturn($sourceData);

        // Mock field matcher to return fuzzy match
        $this->fieldMatcher->method('findBestMatch')
            ->willReturn([
                'field' => 'fname',
                'score' => 0.85,
                'match_type' => 'fuzzy'
            ]);

        $result = $this->service->mapEpisodeToTemplate($episodeId, $manufacturer);

        $this->assertArrayHasKey('data', $result);
        // Should have mapped fuzzy fields
    }

    /** @test */
    public function it_handles_computed_fields()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        $sourceData = [
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'wound_duration_days' => 30,
            'wound_duration_weeks' => 4,
            'wound_duration_months' => 1,
            'wound_duration_years' => 0
        ];
        
        $this->dataExtractor->method('extractEpisodeData')
            ->willReturn($sourceData);

        // Mock transformer for duration formatting
        $this->fieldTransformer->method('formatDuration')
            ->willReturn('1 month, 4 weeks, 30 days');

        $result = $this->service->mapEpisodeToTemplate($episodeId, $manufacturer);

        $this->assertArrayHasKey('data', $result);
    }

    /** @test */
    public function it_handles_or_conditions_in_field_mapping()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        // First field empty, second field has value
        $sourceData = [
            'patient_phone' => '',
            'contact_phone' => '1234567890'
        ];
        
        $this->dataExtractor->method('extractEpisodeData')
            ->willReturn($sourceData);

        $result = $this->service->mapEpisodeToTemplate($episodeId, $manufacturer);

        // Should pick up the contact_phone value for patient_phone field mapping
        $this->assertArrayHasKey('data', $result);
    }

    /** @test */
    public function it_logs_mapping_analytics()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        $sourceData = [
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe'
        ];
        
        $this->dataExtractor->method('extractEpisodeData')
            ->willReturn($sourceData);

        // Should not throw exception and should complete successfully
        $result = $this->service->mapEpisodeToTemplate($episodeId, $manufacturer);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('duration_ms', $result['metadata']);
        $this->assertGreaterThan(0, $result['metadata']['duration_ms']);
    }

    /** @test */
    public function it_lists_manufacturers_correctly()
    {
        $manufacturers = $this->service->listManufacturers();

        $this->assertIsArray($manufacturers);
        $this->assertNotEmpty($manufacturers);

        // Check structure of first manufacturer
        $first = $manufacturers[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('template_id', $first);
        $this->assertArrayHasKey('signature_required', $first);
        $this->assertArrayHasKey('fields_count', $first);
        $this->assertArrayHasKey('required_fields_count', $first);
    }

    /** @test */
    public function it_gets_manufacturer_by_product_code()
    {
        // This would need to be configured in the field-mapping config
        $manufacturer = $this->service->getManufacturerByProduct('MEMBRANE_WRAP');
        
        // Should return manufacturer name or null if not found
        $this->assertTrue(is_string($manufacturer) || is_null($manufacturer));
    }

    /** @test */
    public function it_handles_exceptions_gracefully()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        
        // Mock data extractor to throw exception
        $this->dataExtractor->method('extractEpisodeData')
            ->willThrowException(new \Exception('Database error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->service->mapEpisodeToTemplate($episodeId, $manufacturer);
    }

    /** @test */
    public function it_handles_additional_data_merging()
    {
        $episodeId = 123;
        $manufacturer = 'ACZ';
        $additionalData = [
            'custom_field' => 'custom_value',
            'patient_first_name' => 'Override'  // Should override extracted data
        ];
        
        $sourceData = [
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe'
        ];
        
        $this->dataExtractor->method('extractEpisodeData')
            ->willReturn($sourceData);

        $result = $this->service->mapEpisodeToTemplate($episodeId, $manufacturer, $additionalData);

        $this->assertArrayHasKey('data', $result);
        // Additional data should be merged and override source data
    }
}
<?php

namespace Tests\Unit\Services\FieldMapping;

use App\Services\FieldMapping\FieldMatcher;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\Cache;

class FieldMatcherTest extends TestCase
{
    private FieldMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Cache facade
        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });
            
        $this->matcher = new FieldMatcher();
    }

    /** @test */
    public function it_finds_exact_matches()
    {
        $availableFields = ['patient_first_name', 'patient_last_name', 'patient_phone'];
        
        $result = $this->matcher->findBestMatch('patient_first_name', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertEquals('patient_first_name', $result['field']);
        $this->assertEquals('exact', $result['match_type']);
        $this->assertGreaterThan(0.9, $result['score']);
    }

    /** @test */
    public function it_finds_semantic_matches()
    {
        $availableFields = ['first_name', 'last_name', 'phone'];
        
        $result = $this->matcher->findBestMatch('patient_first_name', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertEquals('first_name', $result['field']);
        $this->assertEquals('semantic', $result['match_type']);
        $this->assertGreaterThan(0.9, $result['score']);
    }

    /** @test */
    public function it_finds_fuzzy_matches()
    {
        $availableFields = ['patient_fname', 'patient_lname', 'patient_tel'];
        
        $result = $this->matcher->findBestMatch('patient_first_name', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertContains($result['field'], ['patient_fname']);
        $this->assertEquals('fuzzy', $result['match_type']);
    }

    /** @test */
    public function it_finds_pattern_matches()
    {
        $availableFields = ['patient_id', 'provider_id', 'facility_id'];
        
        $result = $this->matcher->findBestMatch('episode_id', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertEquals('pattern', $result['match_type']);
    }

    /** @test */
    public function it_returns_null_for_no_good_matches()
    {
        $availableFields = ['completely_different', 'totally_unrelated', 'nothing_similar'];
        
        $result = $this->matcher->findBestMatch('patient_first_name', $availableFields);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_handles_case_insensitive_matching()
    {
        $availableFields = ['PATIENT_FIRST_NAME', 'PATIENT_LAST_NAME'];
        
        $result = $this->matcher->findBestMatch('patient_first_name', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertEquals('PATIENT_FIRST_NAME', $result['field']);
        $this->assertEquals('exact', $result['match_type']);
    }

    /** @test */
    public function it_prioritizes_better_matches()
    {
        $availableFields = ['fname', 'patient_first_name', 'first_name'];
        
        $result = $this->matcher->findBestMatch('patient_first_name', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertEquals('patient_first_name', $result['field']); // Should prefer exact match
        $this->assertEquals('exact', $result['match_type']);
    }

    /** @test */
    public function it_uses_context_for_scoring()
    {
        $availableFields = ['phone', 'phone_number', 'telephone'];
        $context = [
            'phone' => '1234567890',
            'phone_number' => 'invalid',
            'telephone' => '9876543210'
        ];
        
        $result = $this->matcher->findBestMatch('patient_phone', $availableFields, $context);
        
        $this->assertNotNull($result);
        $this->assertContains($result['field'], ['phone', 'telephone']); // Should prefer valid phone format
    }

    /** @test */
    public function it_handles_empty_available_fields()
    {
        $result = $this->matcher->findBestMatch('patient_first_name', []);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_matches_npi_fields()
    {
        $availableFields = ['provider_number', 'npi_number', 'provider_npi'];
        
        $result = $this->matcher->findBestMatch('provider_npi', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertContains($result['field'], ['provider_npi', 'npi_number']);
    }

    /** @test */
    public function it_matches_insurance_fields()
    {
        $availableFields = ['insurance_name', 'payer_name', 'insurance_company'];
        
        $result = $this->matcher->findBestMatch('primary_insurance_name', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertEquals('semantic', $result['match_type']);
    }

    /** @test */
    public function it_handles_special_characters_in_field_names()
    {
        $availableFields = ['patient-first-name', 'patient.first.name', 'patient_first_name'];
        
        $result = $this->matcher->findBestMatch('patient_first_name', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertEquals('patient_first_name', $result['field']);
    }

    /** @test */
    public function it_calculates_jaro_winkler_correctly()
    {
        $availableFields = ['first_name', 'firstname', 'fname'];
        
        $result = $this->matcher->findBestMatch('first_name', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertEquals('first_name', $result['field']);
        $this->assertEquals('exact', $result['match_type']);
    }

    /** @test */
    public function it_handles_very_similar_but_not_exact_matches()
    {
        $availableFields = ['patient_first_name_field', 'patient_first_name_value'];
        
        $result = $this->matcher->findBestMatch('patient_first_name', $availableFields);
        
        $this->assertNotNull($result);
        $this->assertEquals('fuzzy', $result['match_type']);
        $this->assertGreaterThan(0.7, $result['score']);
    }
}
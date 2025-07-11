<?php

use PHPUnit\Framework\TestCase;
use App\Services\CanonicalFieldMappingService;

class CanonicalFieldMappingServiceTest extends TestCase
{
    public function testInjectDocusealPrefillDataAddsDefaultValues()
    {
        $service = new CanonicalFieldMappingService();
        $mappedData = [];
        $result = $service->injectDocusealPrefillData($mappedData);
        $this->assertEquals('Default Signature', $result['signature']);
        $this->assertNull($result['signatureImage']);
    }

    public function testInjectDocusealPrefillDataDoesNotOverrideExistingValues()
    {
        $service = new CanonicalFieldMappingService();
        $mappedData = [
            'signature' => 'Existing Signature',
            'signatureImage' => 'existing_image.png'
        ];
        $result = $service->injectDocusealPrefillData($mappedData);
        $this->assertEquals('Existing Signature', $result['signature']);
        $this->assertEquals('existing_image.png', $result['signatureImage']);
    }
}

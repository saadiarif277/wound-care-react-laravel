<?php

use PHPUnit\Framework\TestCase;
use App\Services\CanonicalFieldService;

class CanonicalFieldServiceTest extends TestCase
{
    public function testInjectDocusealPrefillDataAddsDefaultValues()
    {
        $service = new CanonicalFieldService();
        $fields = [];
        $result = $service->injectDocusealPrefillData($fields);
        $this->assertEquals('Default Signature', $result['signature']);
        $this->assertNull($result['signatureImage']);
    }

    public function testInjectDocusealPrefillDataDoesNotOverrideExistingValues()
    {
        $service = new CanonicalFieldService();
        $fields = [
            'signature' => 'PreExisting Signature',
            'signatureImage' => 'preexisting_image.png'
        ];
        $result = $service->injectDocusealPrefillData($fields);
        $this->assertEquals('PreExisting Signature', $result['signature']);
        $this->assertEquals('preexisting_image.png', $result['signatureImage']);
    }
}

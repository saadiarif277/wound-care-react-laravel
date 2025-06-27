<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Templates\DocuSealBuilder;
use App\Models\DocuSeal\DocuSealTemplate;
use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use Exception;

class DocuSealBuilderTest extends TestCase
{
    use RefreshDatabase;

    private DocuSealBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new DocuSealBuilder();
    }

    public function test_get_template_by_manufacturer()
    {
        $manufacturer = Manufacturer::factory()->create();
        $template = DocuSealTemplate::create([
            'name' => 'Test Template',
            'docuseal_template_id' => 'tmpl1',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => [],
            'is_active' => true,
        ]);

        $result = $this->builder->getTemplate($manufacturer->id, null);

        $this->assertInstanceOf(DocuSealTemplate::class, $result);
        $this->assertEquals($template->id, $result->id);
    }

    public function test_get_template_by_product_code()
    {
        $manufacturer = Manufacturer::factory()->create();
        $template = DocuSealTemplate::create([
            'name' => 'Product Template',
            'docuseal_template_id' => 'tmpl2',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => ['A6234', 'A6235'],
            'is_active' => true,
        ]);

        $result = $this->builder->getTemplate($manufacturer->id, 'A6234');

        $this->assertInstanceOf(DocuSealTemplate::class, $result);
        $this->assertEquals($template->id, $result->id);
    }

    public function test_get_template_throws_exception_when_missing()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No DocuSeal template found');
        
        $this->builder->getTemplate('nonexistent-id', 'unknowncode');
    }

    public function test_get_generic_template_when_no_specific_product_match()
    {
        $manufacturer = Manufacturer::factory()->create();
        
        // Create generic template (no product codes)
        $genericTemplate = DocuSealTemplate::create([
            'name' => 'Generic Template',
            'docuseal_template_id' => 'generic-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => [],
            'is_active' => true,
        ]);
        
        // Create specific template for different product
        $specificTemplate = DocuSealTemplate::create([
            'name' => 'Specific Template',
            'docuseal_template_id' => 'specific-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => ['B9999'],
            'is_active' => true,
        ]);

        // Request template for non-matching product code
        $result = $this->builder->getTemplate($manufacturer->id, 'A1234');

        $this->assertEquals($genericTemplate->id, $result->id);
        $this->assertNotEquals($specificTemplate->id, $result->id);
    }

    public function test_get_only_active_templates()
    {
        $manufacturer = Manufacturer::factory()->create();
        
        // Create inactive template
        $inactiveTemplate = DocuSealTemplate::create([
            'name' => 'Inactive Template',
            'docuseal_template_id' => 'inactive-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => ['A1234'],
            'is_active' => false,
        ]);
        
        // Create active template
        $activeTemplate = DocuSealTemplate::create([
            'name' => 'Active Template',
            'docuseal_template_id' => 'active-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => ['A1234'],
            'is_active' => true,
        ]);

        $result = $this->builder->getTemplate($manufacturer->id, 'A1234');

        $this->assertEquals($activeTemplate->id, $result->id);
        $this->assertNotEquals($inactiveTemplate->id, $result->id);
    }

    public function test_case_insensitive_product_code_search()
    {
        $manufacturer = Manufacturer::factory()->create();
        
        $template = DocuSealTemplate::create([
            'name' => 'Test Template',
            'docuseal_template_id' => 'test-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => ['A6234', 'B5678'],
            'is_active' => true,
        ]);

        // Test with lowercase
        $result1 = $this->builder->getTemplate($manufacturer->id, 'a6234');
        // Test with uppercase
        $result2 = $this->builder->getTemplate($manufacturer->id, 'A6234');
        // Test with mixed case
        $result3 = $this->builder->getTemplate($manufacturer->id, 'a6234');

        $this->assertEquals($template->id, $result1->id);
        $this->assertEquals($template->id, $result2->id);
        $this->assertEquals($template->id, $result3->id);
    }

    public function test_prefers_specific_template_over_generic()
    {
        $manufacturer = Manufacturer::factory()->create();
        
        // Create generic template
        $genericTemplate = DocuSealTemplate::create([
            'name' => 'Generic Template',
            'docuseal_template_id' => 'generic-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => [],
            'is_active' => true,
        ]);
        
        // Create specific template
        $specificTemplate = DocuSealTemplate::create([
            'name' => 'Specific Template',
            'docuseal_template_id' => 'specific-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => ['A6234'],
            'is_active' => true,
        ]);

        $result = $this->builder->getTemplate($manufacturer->id, 'A6234');

        $this->assertEquals($specificTemplate->id, $result->id);
        $this->assertNotEquals($genericTemplate->id, $result->id);
    }

    public function test_handles_templates_with_multiple_product_codes()
    {
        $manufacturer = Manufacturer::factory()->create();
        
        $template = DocuSealTemplate::create([
            'name' => 'Multi Product Template',
            'docuseal_template_id' => 'multi-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => ['A1111', 'A2222', 'A3333', 'A4444'],
            'is_active' => true,
        ]);

        // Test with different product codes from the list
        $result1 = $this->builder->getTemplate($manufacturer->id, 'A2222');
        $result2 = $this->builder->getTemplate($manufacturer->id, 'A4444');
        $result3 = $this->builder->getTemplate($manufacturer->id, 'A1111');

        $this->assertEquals($template->id, $result1->id);
        $this->assertEquals($template->id, $result2->id);
        $this->assertEquals($template->id, $result3->id);
    }

    public function test_returns_most_recent_template_when_multiple_match()
    {
        $manufacturer = Manufacturer::factory()->create();
        
        // Create older template
        $olderTemplate = DocuSealTemplate::create([
            'name' => 'Older Template',
            'docuseal_template_id' => 'older-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => ['A6234'],
            'is_active' => true,
            'created_at' => now()->subDays(5),
        ]);
        
        // Create newer template
        $newerTemplate = DocuSealTemplate::create([
            'name' => 'Newer Template',
            'docuseal_template_id' => 'newer-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => ['A6234'],
            'is_active' => true,
            'created_at' => now()->subDays(1),
        ]);

        $result = $this->builder->getTemplate($manufacturer->id, 'A6234');

        $this->assertEquals($newerTemplate->id, $result->id);
        $this->assertNotEquals($olderTemplate->id, $result->id);
    }

    public function test_handles_null_product_code()
    {
        $manufacturer = Manufacturer::factory()->create();
        
        $genericTemplate = DocuSealTemplate::create([
            'name' => 'Generic Template',
            'docuseal_template_id' => 'generic-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => [],
            'is_active' => true,
        ]);

        $result = $this->builder->getTemplate($manufacturer->id, null);

        $this->assertInstanceOf(DocuSealTemplate::class, $result);
        $this->assertEquals($genericTemplate->id, $result->id);
    }

    public function test_handles_empty_product_code()
    {
        $manufacturer = Manufacturer::factory()->create();
        
        $genericTemplate = DocuSealTemplate::create([
            'name' => 'Generic Template',
            'docuseal_template_id' => 'generic-tmpl',
            'manufacturer_id' => $manufacturer->id,
            'folder_name' => 'TestFolder',
            'product_codes' => [],
            'is_active' => true,
        ]);

        $result = $this->builder->getTemplate($manufacturer->id, '');

        $this->assertInstanceOf(DocuSealTemplate::class, $result);
        $this->assertEquals($genericTemplate->id, $result->id);
    }
}
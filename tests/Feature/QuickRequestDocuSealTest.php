<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Templates\DocusealBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Mockery;
use Illuminate\Support\Facades\Schema;

class QuickRequestDocusealTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
    }

    /** @test */
    public function it_returns_builder_token_and_url()
    {
        // Prepare user and authentication
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'name'  => 'Test User',
            'email' => 'user@example.com',
        ]);
        $this->actingAs($user, 'sanctum');

        // Prepare manufacturer and template
        $manufacturer = Manufacturer::factory()->create();
        $template = DocusealTemplate::factory()->create([
            'manufacturer_id'      => $manufacturer->id,
            'docuseal_template_id' => 'tpl-123',
            'document_type'        => 'IVR',
            'is_active'            => true,
        ]);

        // Mock DocusealBuilder service to return expected token and URL
        $mockBuilder = Mockery::mock(\App\Services\Templates\DocusealBuilder::class);
        $mockBuilder->shouldReceive('generateBuilderToken')
            ->once()
            ->with($manufacturer->id, 'PROD1')
            ->andReturn([
                $template->docuseal_template_id,
                'jwt-token-abc',
                'https://api.docuseal.com/builder'
            ]);
        $this->app->instance(DocusealBuilder::class, $mockBuilder);

        // Call the endpoint
        $response = $this->postJson('/api/v1/quick-request/docuseal/generate-builder-token', [
            'manufacturerId' => $manufacturer->id,
            'productCode'    => 'PROD1',
        ]);

        // Assert response structure and values
        $response->assertStatus(200)
                 ->assertJson([
                     'builderToken' => 'jwt-token-abc',
                     'builderUrl'   => 'https://api.docuseal.com/builder',
                 ]);
    }
}
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SubmitPreAuthorizationJob;
use App\Models\PreAuthorization;
use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SubmitPreAuthorizationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set config for testing
        config(['payers.submission_endpoint' => 'https://test-payer.example.com/submit']);
    }

    /** @test */
    public function it_handles_successful_submission()
    {
        // Arrange
        $user = User::factory()->create();
        $productRequest = ProductRequest::factory()->create();
        $preAuth = PreAuthorization::factory()->create([
            'product_request_id' => $productRequest->id,
            'status' => 'submitted',
        ]);

        Http::fake([
            'test-payer.example.com/*' => Http::response([
                'transaction_id' => 'TX123456',
                'confirmation_number' => 'CONF789',
                'status' => 'received'
            ], 200)
        ]);

        // Act
        $job = new SubmitPreAuthorizationJob($preAuth->id);
        $job->handle();

        // Assert
        $preAuth->refresh();
        $this->assertEquals('processing', $preAuth->status);
        $this->assertEquals('TX123456', $preAuth->payer_transaction_id);
        $this->assertEquals('CONF789', $preAuth->payer_confirmation);
    }

    /** @test */
    public function it_handles_submission_failure()
    {
        // Arrange
        $user = User::factory()->create();
        $productRequest = ProductRequest::factory()->create();
        $preAuth = PreAuthorization::factory()->create([
            'product_request_id' => $productRequest->id,
            'status' => 'submitted',
        ]);

        Http::fake([
            'test-payer.example.com/*' => Http::response([], 500)
        ]);

        // Act & Assert
        $job = new SubmitPreAuthorizationJob($preAuth->id);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payer system responded with error: 500');
        
        $job->handle();
    }

    /** @test */
    public function it_handles_missing_pre_authorization()
    {
        // Arrange
        Log::shouldReceive('error')
            ->once()
            ->with('PreAuthorization not found for job', ['pre_auth_id' => 999]);

        // Act
        $job = new SubmitPreAuthorizationJob(999);
        $job->handle();

        // Assert - No exception should be thrown, just logged
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_missing_config()
    {
        // Arrange
        config(['payers.submission_endpoint' => null]);
        
        $user = User::factory()->create();
        $productRequest = ProductRequest::factory()->create();
        $preAuth = PreAuthorization::factory()->create([
            'product_request_id' => $productRequest->id,
            'status' => 'submitted',
        ]);

        // Act & Assert
        $job = new SubmitPreAuthorizationJob($preAuth->id);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payer submission endpoint not configured');
        
        $job->handle();
    }

    /** @test */
    public function it_updates_status_on_failure()
    {
        // Arrange
        $user = User::factory()->create();
        $productRequest = ProductRequest::factory()->create();
        $preAuth = PreAuthorization::factory()->create([
            'product_request_id' => $productRequest->id,
            'status' => 'submitted',
        ]);

        $exception = new \Exception('Test failure');

        // Act
        $job = new SubmitPreAuthorizationJob($preAuth->id);
        $job->failed($exception);

        // Assert
        $preAuth->refresh();
        $this->assertEquals('submission_failed', $preAuth->status);
        $this->assertArrayHasKey('error', $preAuth->payer_response);
        $this->assertEquals('Test failure', $preAuth->payer_response['error']);
    }
} 
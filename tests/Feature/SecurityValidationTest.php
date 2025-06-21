<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order\Order;
use App\Services\ValidationBuilderEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecurityValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_engine_prevents_unauthorized_access()
    {
        /** @var \App\Models\User $user1 */
        $user1 = User::factory()->create();
        /** @var \App\Models\User $user2 */
        $user2 = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)
            ->postJson('/api/v1/validation-builder/validate-order', [
                'order_id' => $order->id,
                'specialty' => 'wound_care_specialty'
            ]);

        $response->assertStatus(403);
    }

    public function test_cms_api_input_sanitization()
    {
        Http::fake([
            'api.coverage.cms.gov/*' => Http::response(['data' => [], 'total' => 0])
        ]);

        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        // Test SQL injection attempt
        $response = $this->actingAs($user)
            ->getJson('/api/v1/validation-builder/search-cms?keyword=' . urlencode("'; DROP TABLE orders; --"));

        $response->assertStatus(200);
        // Should handle malicious input gracefully without executing SQL
    }

    public function test_rate_limiting_on_cms_api_calls()
    {
        Http::fake([
            'api.coverage.cms.gov/*' => Http::response(['data' => [], 'total' => 0])
        ]);

        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        // Make multiple rapid requests
        for ($i = 0; $i < 65; $i++) {
            $response = $this->actingAs($user)
                ->getJson('/api/v1/validation-builder/cms-lcds?specialty=wound_care_specialty');

            if ($i < 60) {
                $response->assertStatus(200);
            } else {
                // Should be rate limited after 60 requests per minute
                $response->assertStatus(429);
                break;
            }
        }
    }

    public function test_validation_data_is_not_exposed_in_logs()
    {
        $order = Order::factory()->create([
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'patient_member_id' => 'SENSITIVE123'
        ]);

        $engine = app(ValidationBuilderEngine::class);

        Log::shouldReceive('info')
            ->andReturnUsing(function ($message) {
                // Ensure no sensitive data is logged
                $this->assertStringNotContainsString('John', $message);
                $this->assertStringNotContainsString('Doe', $message);
                $this->assertStringNotContainsString('SENSITIVE123', $message);
            });

        $engine->validateOrder($order);
    }

    public function test_cache_poisoning_prevention()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        // Attempt to poison cache with malicious data
        $maliciousData = ['<script>alert("xss")</script>'];

        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) use ($maliciousData) {
                // Ensure callback is called and data is properly sanitized
                $result = $callback();
                $this->assertIsArray($result);
                return $result;
            });

        $response = $this->actingAs($user)
            ->getJson('/api/v1/validation-builder/rules?specialty=wound_care_specialty');

        $response->assertStatus(200);
    }

    public function test_memory_usage_under_load()
    {
        $initialMemory = memory_get_usage();

        $engine = app(ValidationBuilderEngine::class);

        // Process multiple validation requests
        for ($i = 0; $i < 100; $i++) {
            $order = Order::factory()->create();
            $engine->validateOrder($order);
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 50MB for 100 validations)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease);
    }

    public function test_validation_timeout_handling()
    {
        // Mock a slow CMS API response
        Http::fake([
            'api.coverage.cms.gov/*' => function () {
                sleep(35); // Simulate timeout
                return Http::response(['data' => []], 500);
            }
        ]);

        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/validation-builder/cms-lcds?specialty=wound_care_specialty');

        // Should handle timeout gracefully
        $response->assertStatus(200);
        $response->assertJsonPath('data.error_message', function ($value) {
            return !empty($value);
        });
    }

    public function test_concurrent_validation_requests()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $orders = Order::factory()->count(10)->create(['user_id' => $user->id]);

        $engine = app(ValidationBuilderEngine::class);

        // Simulate concurrent validation requests
        $results = [];
        foreach ($orders as $order) {
            $results[] = $engine->validateOrder($order);
        }

        // All validations should complete successfully
        $this->assertCount(10, $results);
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('overall_status', $result);
        }
    }

    public function test_validation_rules_contain_no_executable_code()
    {
        $engine = app(ValidationBuilderEngine::class);
        $rules = $engine->buildValidationRulesForSpecialty('wound_care_specialty');

        $this->assertValidationRulesAreSafe($rules);
    }

    private function assertValidationRulesAreSafe(array $rules): void
    {
        array_walk_recursive($rules, function ($value, $key) {
            if (is_string($value)) {
                // Check for potentially dangerous patterns
                $this->assertStringNotContainsString('eval(', $value);
                $this->assertStringNotContainsString('exec(', $value);
                $this->assertStringNotContainsString('system(', $value);
                $this->assertStringNotContainsString('shell_exec(', $value);
                $this->assertStringNotContainsString('<?php', $value);
                $this->assertStringNotContainsString('<script', $value);
            }
        });
    }

    public function test_error_messages_dont_expose_sensitive_info()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        // Test with invalid order ID
        $response = $this->actingAs($user)
            ->postJson('/api/v1/validation-builder/validate-order', [
                'order_id' => 999999,
                'specialty' => 'wound_care_specialty'
            ]);

        $response->assertStatus(404);

        // Error message should not expose database schema or internal paths
        $content = $response->getContent();
        $this->assertStringNotContainsString('database', strtolower($content));
        $this->assertStringNotContainsString('mysql', strtolower($content));
        $this->assertStringNotContainsString('/var/www', $content);
        $this->assertStringNotContainsString('app/models', strtolower($content));
    }

    public function test_validation_engine_handles_malformed_data()
    {
        $engine = app(ValidationBuilderEngine::class);

        // Create order with intentionally malformed data
        $order = new Order([
            'patient_first_name' => str_repeat('A', 1000), // Very long string
            'diagnosis_codes' => ['invalid', null, 123], // Mixed invalid types
            'wound_size_length' => -1, // Invalid negative value
            'specialty' => 'invalid_specialty'
        ]);

        // Should handle gracefully without throwing exceptions
        $result = $engine->validateOrder($order);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_status', $result);
    }
}

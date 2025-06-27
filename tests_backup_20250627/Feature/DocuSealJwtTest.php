<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Order\Order;
use App\Models\Users\Organization\Organization;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class DocuSealJwtTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->organization = Organization::create([
            'id' => Str::uuid(),
            'name' => 'Test Organization',
        ]);

        // Create user with proper role and permissions
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'current_organization_id' => $this->organization->id,
        ]);

        // Create role with permission
        $role = Role::create(['name' => 'office-manager']);
        $permission = Permission::create(['name' => 'manage-orders']);
        $role->permissions()->attach($permission);
        $this->user->assignRole($role);
    }

    public function test_can_generate_jwt_token_without_order()
    {
        // Set up DocuSeal API key
        config(['services.docuseal.api_key' => 'test-api-key']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/admin/docuseal/generate-token', [
                'template_id' => 'test-template-123',
                'name' => 'Test Form',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'expires_at',
            ]);

        // Verify JWT token can be decoded
        $token = $response->json('token');
        $decoded = JWT::decode($token, new Key('test-api-key', 'HS256'));

        $this->assertEquals($this->user->email, $decoded->user_email);
        $this->assertEquals('test-template-123', $decoded->template_id);
        $this->assertEquals('Test Form', $decoded->name);
        $this->assertTrue(property_exists($decoded, 'iat'));
        $this->assertTrue(property_exists($decoded, 'exp'));
    }

    public function test_can_generate_jwt_token_with_order()
    {
        // Set up DocuSeal API key
        config(['services.docuseal.api_key' => 'test-api-key']);

        // Create an order
        $order = Order::create([
            'id' => Str::uuid(),
            'order_number' => 'TEST-001',
            'organization_id' => $this->organization->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/admin/docuseal/generate-token', [
                'template_id' => 'test-template-123',
                'name' => 'Test Form',
                'order_id' => $order->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'expires_at',
            ]);

        // Verify JWT token contains order metadata
        $token = $response->json('token');
        $decoded = JWT::decode($token, new Key('test-api-key', 'HS256'));

        $this->assertTrue(property_exists($decoded, 'metadata'));
        $this->assertEquals($order->id, $decoded->metadata->order_id);
        $this->assertEquals($order->order_number, $decoded->metadata->order_number);
        $this->assertEquals($order->organization_id, $decoded->metadata->organization_id);
    }

    public function test_cannot_generate_token_without_api_key()
    {
        // Ensure no API key is set
        config(['services.docuseal.api_key' => null]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/admin/docuseal/generate-token', [
                'template_id' => 'test-template-123',
                'name' => 'Test Form',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to generate token',
                'message' => 'DocuSeal API key is not configured',
            ]);
    }

    public function test_cannot_generate_token_for_unauthorized_order()
    {
        config(['services.docuseal.api_key' => 'test-api-key']);

        // Create another organization and order
        $otherOrg = Organization::create([
            'id' => Str::uuid(),
            'name' => 'Other Organization',
        ]);

        $order = Order::create([
            'id' => Str::uuid(),
            'order_number' => 'OTHER-001',
            'organization_id' => $otherOrg->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/admin/docuseal/generate-token', [
                'template_id' => 'test-template-123',
                'name' => 'Test Form',
                'order_id' => $order->id,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized access to order',
            ]);
    }

    public function test_validates_required_fields()
    {
        config(['services.docuseal.api_key' => 'test-api-key']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/admin/docuseal/generate-token', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['template_id', 'name']);
    }

    public function test_token_expires_after_30_minutes()
    {
        config(['services.docuseal.api_key' => 'test-api-key']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/admin/docuseal/generate-token', [
                'template_id' => 'test-template-123',
                'name' => 'Test Form',
            ]);

        $response->assertStatus(200);

        $token = $response->json('token');
        $decoded = JWT::decode($token, new Key('test-api-key', 'HS256'));

        // Check that expiration is approximately 30 minutes in the future
        $expectedExp = time() + (60 * 30);
        $this->assertEqualsWithDelta($expectedExp, $decoded->exp, 5); // Allow 5 seconds variance
    }
}

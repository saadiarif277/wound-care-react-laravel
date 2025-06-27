<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $provider;
    protected $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create account
        $this->account = Account::create(['name' => 'Test Account']);

        // Create provider user
        $this->provider = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'provider@test.com',
            'first_name' => 'Test',
            'last_name' => 'Provider',
        ]);

        // Create provider role with permissions
        $this->createProviderRoleWithPermissions();
    }

    private function createProviderRoleWithPermissions()
    {
        // Create permissions
        $permissions = [
            'view-dashboard',
            'create-product-requests',
            'view-product-requests',
            'view-request-status',
            'view-mac-validation',
            'manage-mac-validation',
            'view-eligibility',
            'manage-eligibility',
            'view-pre-authorization',
            'manage-pre-authorization',
            'view-products',
            'view-national-asp',
            'view-orders',
            'create-orders',
        ];

        foreach ($permissions as $permissionSlug) {
            Permission::firstOrCreate([
                'slug' => $permissionSlug,
                'name' => ucwords(str_replace('-', ' ', $permissionSlug)),
                'guard_name' => 'web',
            ]);
        }

        // Create provider role
        $providerRole = Role::firstOrCreate([
            'slug' => 'provider',
            'name' => 'Healthcare Provider',
            'description' => 'Healthcare provider with access to patient care tools',
        ]);

        // Attach permissions to role
        $permissionIds = Permission::whereIn('slug', $permissions)->pluck('id');
        $providerRole->permissions()->sync($permissionIds);

        // Assign role to user
        $this->provider->roles()->attach($providerRole);
    }

    /** @test */
    public function provider_can_access_dashboard()
    {
        $response = $this->actingAs($this->provider)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard/Provider/ProviderDashboard')
        );
    }

    /** @test */
    public function provider_can_access_new_product_request_form()
    {
        $response = $this->actingAs($this->provider)->get('/product-requests/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('ProductRequest/Create')
        );
    }

    /** @test */
    public function provider_can_access_product_requests_index()
    {
        $response = $this->actingAs($this->provider)->get('/product-requests');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('ProductRequest/Index')
        );
    }

    /** @test */
    public function provider_can_access_eligibility_index()
    {
        $response = $this->actingAs($this->provider)->get('/eligibility');

        $response->assertStatus(200);
    }

    /** @test */
    public function provider_can_access_mac_validation()
    {
        $response = $this->actingAs($this->provider)->get('/mac-validation');

        $response->assertStatus(200);
    }

    /** @test */
    public function provider_can_access_pre_authorization()
    {
        $response = $this->actingAs($this->provider)->get('/pre-authorization');

        $response->assertStatus(200);
    }

    /** @test */
    public function provider_can_access_products_catalog()
    {
        $response = $this->actingAs($this->provider)->get('/products');

        $response->assertStatus(200);
    }

    /** @test */
    public function provider_has_correct_permissions()
    {
        $expectedPermissions = [
            'view-dashboard',
            'create-product-requests',
            'view-product-requests',
            'view-request-status',
            'view-mac-validation',
            'manage-mac-validation',
            'view-eligibility',
            'manage-eligibility',
            'view-pre-authorization',
            'manage-pre-authorization',
            'view-products',
            'view-national-asp',
            'view-orders',
            'create-orders',
        ];

        foreach ($expectedPermissions as $permission) {
            $this->assertTrue(
                $this->provider->hasPermission($permission),
                "Provider should have permission: {$permission}"
            );
        }
    }

    /** @test */
    public function provider_cannot_access_admin_routes()
    {
        // Test that provider cannot access admin-only routes
        $adminRoutes = [
            '/admin/users',
            '/admin/organizations',
            '/orders/center',
            '/commission/management',
        ];

        foreach ($adminRoutes as $route) {
            $response = $this->actingAs($this->provider)->get($route);
            $response->assertStatus(403);
        }
    }

    /** @test */
    public function dashboard_displays_correct_data_for_provider()
    {
        $response = $this->actingAs($this->provider)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard/Provider/ProviderDashboard')
                ->has('user')
                ->has('dashboardData')
                ->has('roleRestrictions')
                ->where('user.role', 'provider')
        );
    }
}

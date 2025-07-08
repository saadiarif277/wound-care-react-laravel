<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\Role;
use App\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find the provider role
        $providerRole = Role::where('slug', 'provider')->first();
        
        // Find the create-product-requests permission
        $permission = Permission::where('slug', 'create-product-requests')->first();
        
        // If both exist, attach the permission to the role
        if ($providerRole && $permission) {
            // Check if the permission is not already attached
            if (!$providerRole->permissions()->where('permission_id', $permission->id)->exists()) {
                $providerRole->permissions()->attach($permission->id);
                
                Log::info('Added create-product-requests permission to provider role');
            }
        } else {
            Log::warning('Could not find provider role or create-product-requests permission', [
                'provider_role_found' => (bool) $providerRole,
                'permission_found' => (bool) $permission
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find the provider role
        $providerRole = Role::where('slug', 'provider')->first();
        
        // Find the create-product-requests permission
        $permission = Permission::where('slug', 'create-product-requests')->first();
        
        // If both exist, detach the permission from the role
        if ($providerRole && $permission) {
            $providerRole->permissions()->detach($permission->id);
            
            Log::info('Removed create-product-requests permission from provider role');
        }
    }
};

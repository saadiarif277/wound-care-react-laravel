<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create PDF management permissions if they don't exist
        $permissions = [
            [
                'name' => 'Manage PDF Templates',
                'slug' => 'manage-pdf-templates',
                'description' => 'Can upload, edit, and manage PDF templates',
            ],
            [
                'name' => 'View PDF Reports',
                'slug' => 'view-pdf-reports',
                'description' => 'Can view PDF-related reports and analytics',
            ],
            [
                'name' => 'Generate PDFs',
                'slug' => 'generate-pdfs',
                'description' => 'Can generate PDF documents from templates',
            ],
        ];

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['slug' => $permissionData['slug']],
                $permissionData
            );
        }

        // Assign permissions to admin roles
        $adminRoles = ['msc-admin', 'super-admin', 'superadmin'];
        
        foreach ($adminRoles as $roleName) {
            $role = Role::where('slug', $roleName)->first();
            
            if ($role) {
                // Attach all PDF permissions to admin roles
                $pdfPermissions = Permission::whereIn('slug', [
                    'manage-pdf-templates',
                    'view-pdf-reports',
                    'generate-pdfs'
                ])->get();
                
                foreach ($pdfPermissions as $permission) {
                    if (!$role->permissions()->where('permission_id', $permission->id)->exists()) {
                        $role->permissions()->attach($permission->id);
                    }
                }
            }
        }

        // Also give office managers the ability to generate PDFs
        $officeManagerRole = Role::where('slug', 'office-manager')->first();
        if ($officeManagerRole) {
            $generatePermission = Permission::where('slug', 'generate-pdfs')->first();
            if ($generatePermission && !$officeManagerRole->permissions()->where('permission_id', $generatePermission->id)->exists()) {
                $officeManagerRole->permissions()->attach($generatePermission->id);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove permissions from roles
        $permissions = Permission::whereIn('slug', [
            'manage-pdf-templates',
            'view-pdf-reports',
            'generate-pdfs'
        ])->get();

        foreach ($permissions as $permission) {
            // Detach from all roles
            $permission->roles()->detach();
        }

        // Delete the permissions
        Permission::whereIn('slug', [
            'manage-pdf-templates',
            'view-pdf-reports',
            'generate-pdfs'
        ])->delete();
    }
};
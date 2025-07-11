<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Role;
use App\Models\Permission;

class CheckRolePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:role-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check role-permission assignments for the 4-tier financial system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking 4-tier financial permission assignments...');

        // First, let's see what roles actually exist
        $this->info("\n=== Available Roles ===");
        $allRoles = Role::all(['id', 'name']);
        foreach ($allRoles as $role) {
            $this->line("ID: {$role->id} | Name: {$role->name}");
        }

        // Expected assignments using correct role names
        $expectedAssignments = [
            'Healthcare Provider' => ['common-financial-data', 'my-financial-data'],
            'MSC Administrator' => ['view-all-financial-data'],
            'Super Admin' => ['view-all-financial-data'],
            'Office Manager' => ['no-financial-data']
        ];

        foreach ($expectedAssignments as $roleName => $expectedPermissions) {
            $this->info("\n=== {$roleName} Role ===");
            
            $role = Role::where('name', $roleName)->first();
            
            if (!$role) {
                $this->error("Role '{$roleName}' not found!");
                continue;
            }

            $this->line("Role ID: {$role->id}");

            // Get all financial permissions for this role
            $assignedFinancialPermissions = $role->permissions()
                ->where('permissions.slug', 'like', '%financial%')
                ->pluck('permissions.slug', 'permissions.id')
                ->toArray();

            $this->line("Assigned financial permissions:");
            if (empty($assignedFinancialPermissions)) {
                $this->line("  <comment>None</comment>");
            } else {
                foreach ($assignedFinancialPermissions as $id => $slug) {
                    $this->line("  ID: {$id} | Slug: {$slug}");
                }
            }

            // Check if all expected permissions are assigned
            $this->line("\nExpected permissions check:");
            foreach ($expectedPermissions as $expectedSlug) {
                $isAssigned = in_array($expectedSlug, $assignedFinancialPermissions);
                $status = $isAssigned ? '<info>✓ ASSIGNED</info>' : '<error>✗ MISSING</error>';
                $this->line("  {$expectedSlug}: {$status}");
            }

            // Check for unexpected permissions
            $unexpectedPermissions = array_diff($assignedFinancialPermissions, $expectedPermissions);
            if (!empty($unexpectedPermissions)) {
                $this->line("\nUnexpected financial permissions:");
                foreach ($unexpectedPermissions as $id => $slug) {
                    $this->line("  <comment>ID: {$id} | Slug: {$slug}</comment>");
                }
            }
        }

        // Summary
        $this->info("\n=== Summary ===");
        $this->line("Expected assignments:");
        $this->line("• Healthcare Provider (58, 59): common-financial-data + my-financial-data");
        $this->line("• MSC Administrator & Super Admin (60): view-all-financial-data");
        $this->line("• Office Manager (61): no-financial-data");

        return Command::SUCCESS;
    }
}

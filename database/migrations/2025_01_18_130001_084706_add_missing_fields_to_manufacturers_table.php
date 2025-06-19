<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create manufacturers table if it doesn't exist
        if (!Schema::hasTable('manufacturers')) {
            Schema::create('manufacturers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('address')->nullable();
                $table->string('website')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('slug');
            });
        } else {
            // Add fields to existing table
            Schema::table('manufacturers', function (Blueprint $table) {
                if (!Schema::hasColumn('manufacturers', 'slug')) {
                    $table->string('slug')->unique()->after('name');
                }
                if (!Schema::hasColumn('manufacturers', 'website')) {
                    $table->string('website')->nullable()->after('address');
                }
                if (!Schema::hasColumn('manufacturers', 'notes')) {
                    $table->text('notes')->nullable()->after('website');
                }

                // Add soft deletes
                if (!Schema::hasColumn('manufacturers', 'deleted_at')) {
                    $table->softDeletes();
                }

                // Add contact fields
                if (!Schema::hasColumn('manufacturers', 'contact_email')) {
                    $table->string('contact_email')->nullable()->after('name');
                }

                if (!Schema::hasColumn('manufacturers', 'contact_phone')) {
                    $table->string('contact_phone')->nullable()->after('contact_email');
                }

                if (!Schema::hasColumn('manufacturers', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('notes');
                }

                // Add index for slug if not exists
                if (!Schema::hasIndex('manufacturers', 'manufacturers_slug_index')) {
                    $table->index('slug');
                }

                // Add index for is_active if not exists
                if (Schema::hasColumn('manufacturers', 'is_active') && !Schema::hasIndex('manufacturers', 'manufacturers_is_active_index')) {
                    $table->index('is_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            if (Schema::hasIndex('manufacturers', 'manufacturers_slug_index')) {
                $table->dropIndex(['slug']);
            }
            if (Schema::hasIndex('manufacturers', 'manufacturers_is_active_index')) {
                $table->dropIndex(['is_active']);
            }
            $table->dropSoftDeletes();
            $table->dropColumn(['slug', 'website', 'notes', 'contact_email', 'contact_phone', 'is_active']);

            // Revert address back to text
            $table->text('address')->nullable()->change();
        });
    }
};

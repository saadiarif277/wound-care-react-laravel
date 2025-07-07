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
        // Create order status change history table
        if (!Schema::hasTable('order_status_changes')) {
            Schema::create('order_status_changes', function (Blueprint $table) {
                $table->id();
                $table->uuid('order_id');
                $table->string('previous_status')->nullable();
                $table->string('new_status');
                $table->string('changed_by'); // user_id or system
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable(); // Additional context
                $table->timestamps();

                $table->index(['order_id', 'created_at']);
                $table->index('new_status');
                $table->index('changed_by');
            });
        }

        // Create email notification tracking table
        if (!Schema::hasTable('order_email_notifications')) {
            Schema::create('order_email_notifications', function (Blueprint $table) {
                $table->id();
                $table->uuid('order_id');
                $table->string('notification_type'); // status_change, ivr_sent, etc.
                $table->string('recipient_email');
                $table->string('recipient_name')->nullable();
                $table->string('subject');
                $table->text('content');
                $table->string('status')->default('pending'); // pending, sent, delivered, failed
                $table->string('message_id')->nullable(); // Email service message ID
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable(); // Additional context
                $table->timestamps();

                $table->index(['order_id', 'created_at']);
                $table->index(['notification_type', 'status']);
                $table->index('recipient_email');
            });
        }

        // Add IVR document metadata to existing tables
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'ivr_document_url')) {
                $table->text('ivr_document_url')->nullable()->after('signed_document_url');
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'ivr_audit_log_url')) {
                $table->text('ivr_audit_log_url')->nullable()->after('ivr_document_url');
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'ivr_download_count')) {
                $table->integer('ivr_download_count')->default(0)->after('ivr_audit_log_url');
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'last_ivr_viewed_at')) {
                $table->timestamp('last_ivr_viewed_at')->nullable()->after('ivr_download_count');
            }
        });

        // Add email notification preferences to orders
        Schema::table('product_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('product_requests', 'email_notifications_enabled')) {
                $table->boolean('email_notifications_enabled')->default(true)->after('order_status');
            }
            if (!Schema::hasColumn('product_requests', 'notification_recipients')) {
                $table->json('notification_recipients')->nullable()->after('email_notifications_enabled');
            }
        });

        // Add success notification tracking
        Schema::table('product_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('product_requests', 'last_success_notification_at')) {
                $table->timestamp('last_success_notification_at')->nullable()->after('notification_recipients');
            }
            if (!Schema::hasColumn('product_requests', 'success_notification_count')) {
                $table->integer('success_notification_count')->default(0)->after('last_success_notification_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_changes');
        Schema::dropIfExists('order_email_notifications');

        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            $table->dropColumn([
                'ivr_document_url',
                'ivr_audit_log_url',
                'ivr_download_count',
                'last_ivr_viewed_at'
            ]);
        });

        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropColumn([
                'email_notifications_enabled',
                'notification_recipients',
                'last_success_notification_at',
                'success_notification_count'
            ]);
        });
    }
};

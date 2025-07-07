<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Store manufacturer PDF templates
        Schema::create('manufacturer_pdf_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('manufacturer_id')->index();
            $table->string('template_name');
            $table->string('document_type')->default('ivr'); // ivr, order_form, etc.
            $table->string('file_path'); // Path in Azure storage
            $table->string('azure_container')->default('pdf-templates');
            $table->string('version', 50);
            $table->boolean('is_active')->default(true);
            $table->json('template_fields')->nullable(); // List of fillable fields in PDF
            $table->json('metadata')->nullable(); // Additional template info
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('manufacturer_id')
                ->references('id')
                ->on('manufacturers')
                ->onDelete('cascade');

            $table->index(['manufacturer_id', 'document_type', 'is_active'], 'mfr_pdf_templates_mfr_doc_active_idx');
            $table->index(['template_name', 'version']);
        });

        // Store field mappings between data model and PDF fields
        Schema::create('pdf_field_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('template_id')->index();
            $table->string('pdf_field_name'); // Field name in the PDF
            $table->string('data_source'); // Path in data model (e.g., 'patient.firstName')
            $table->enum('field_type', ['text', 'checkbox', 'radio', 'select', 'signature', 'date', 'image']);
            $table->string('transform_function')->nullable(); // Optional PHP function to transform data
            $table->text('default_value')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('options')->nullable(); // For select/radio fields
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->foreign('template_id')
                ->references('id')
                ->on('manufacturer_pdf_templates')
                ->onDelete('cascade');

            $table->index(['template_id', 'display_order']);
            $table->index('pdf_field_name');
        });

        // Store signature placement configurations
        Schema::create('pdf_signature_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('template_id')->index();
            $table->enum('signature_type', ['patient', 'provider', 'witness', 'sales_rep', 'admin']);
            $table->integer('page_number');
            $table->float('x_position'); // Percentage from left
            $table->float('y_position'); // Percentage from top
            $table->float('width'); // Percentage width
            $table->float('height'); // Percentage height
            $table->string('label')->nullable(); // Text to show near signature
            $table->boolean('is_required')->default(true);
            $table->json('styling')->nullable(); // Border, background, etc.
            $table->timestamps();

            $table->foreign('template_id')
                ->references('id')
                ->on('manufacturer_pdf_templates')
                ->onDelete('cascade');

            $table->index(['template_id', 'signature_type']);
        });

        // Track generated PDFs and their status
        Schema::create('pdf_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('document_id', 36)->unique(); // UUID
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->char('episode_id', 36)->nullable()->index();
            $table->unsignedBigInteger('template_id')->index();
            $table->enum('document_type', ['ivr', 'order_form', 'shipping_label', 'invoice', 'other']);
            $table->enum('status', ['draft', 'generated', 'pending_signature', 'partially_signed', 'completed', 'expired', 'cancelled']);
            $table->string('file_path'); // Path in Azure storage
            $table->string('azure_container')->default('order-pdfs');
            $table->string('azure_blob_url', 500)->nullable();
            $table->json('filled_data')->nullable(); // Data used to fill the PDF
            $table->json('signature_status')->nullable(); // Track which signatures are complete
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->json('metadata')->nullable();
            $table->string('hash', 64)->nullable(); // SHA-256 hash for integrity
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('cascade');

            $table->foreign('episode_id')
                ->references('id')
                ->on('patient_manufacturer_ivr_episodes')
                ->onDelete('cascade');

            $table->foreign('template_id')
                ->references('id')
                ->on('manufacturer_pdf_templates')
                ->onDelete('restrict');

            $table->foreign('generated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['status', 'document_type']);
            $table->index(['expires_at', 'status']);
            $table->index('document_id');
        });

        // Store signature data and audit info
        Schema::create('pdf_signatures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('signature_type', ['patient', 'provider', 'witness', 'sales_rep', 'admin']);
            $table->string('signer_name');
            $table->string('signer_email')->nullable();
            $table->string('signer_title')->nullable();
            $table->text('signature_data'); // Base64 encoded signature image
            $table->string('signature_hash', 64); // SHA-256 hash of signature
            $table->timestamp('signed_at');
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->json('geo_location')->nullable(); // Optional geolocation data
            $table->json('audit_data')->nullable(); // Additional audit information
            $table->boolean('is_valid')->default(true);
            $table->text('invalidation_reason')->nullable();
            $table->timestamps();

            $table->foreign('document_id')
                ->references('id')
                ->on('pdf_documents')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['document_id', 'signature_type']);
            $table->index(['signer_email', 'signed_at']);
            $table->index('signature_hash');
        });

        // PDF document access log for HIPAA compliance
        Schema::create('pdf_access_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('action', ['view', 'download', 'print', 'email', 'forward']);
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->json('context')->nullable(); // Additional context about the access
            $table->timestamp('accessed_at');
            $table->index(['user_id', 'accessed_at']);
            $table->index(['document_id', 'action']);
        });

        // Store PDF transformation functions
        Schema::create('pdf_transform_functions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('function_name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->enum('category', ['date', 'text', 'number', 'boolean', 'array', 'custom']);
            $table->text('function_code'); // PHP code for the transformation
            $table->json('parameters')->nullable(); // Expected parameters
            $table->json('examples')->nullable(); // Example usage
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('pdf_access_logs');
        Schema::dropIfExists('pdf_signatures');
        Schema::dropIfExists('pdf_documents');
        Schema::dropIfExists('pdf_signature_configs');
        Schema::dropIfExists('pdf_field_mappings');
        Schema::dropIfExists('manufacturer_pdf_templates');
        Schema::dropIfExists('pdf_transform_functions');
    }
};
<?php

use App\Http\Controllers\FhirController;

use App\Http\Controllers\CommissionRuleController;
use App\Http\Controllers\CommissionRecordController;
use App\Http\Controllers\CommissionPayoutController;
use App\Http\Controllers\Api\EligibilityController;
use App\Http\Controllers\Api\ValidationBuilderController;
use App\Http\Controllers\Api\ClinicalOpportunitiesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Commission\CommissionController;
use App\Http\Controllers\RBACController;
// Deprecated controllers removed - AccessControlController, CustomerManagementController
use App\Http\Controllers\Api\V1\ProviderOnboardingController;
use App\Http\Controllers\Api\V1\ProviderProfileController;
use App\Http\Controllers\Api\V1\QuickRequestController;
use App\Http\Controllers\Api\ProductRequestPatientController;
use App\Http\Controllers\Api\ProductRequestClinicalAssessmentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\Api\MedicareMacValidationController;
use App\Http\Controllers\Admin\ProviderManagementController;
use App\Http\Controllers\Api\OrderReviewController;
// use App\Http\Controllers\Api\TemplateMappingController; // Commented out - controller doesn't exist
use App\Http\Controllers\Api\DocumentIntelligenceController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\ManufacturerController;
use App\Http\Controllers\Api\V1\SalesRepController;
use App\Http\Controllers\Api\V1\SalesRepCommissionController;

// Medicare MAC Validation Routes - Organized by Specialty
Route::prefix('v1')->group(function () {

    // Order-specific Medicare validation routes
    Route::prefix('orders/{order_id}')->group(function () {
        Route::post('medicare-validation', [MedicareMacValidationController::class, 'validateOrder'])->name('medicare.validate');
        Route::get('medicare-validation', [MedicareMacValidationController::class, 'getValidation'])->name('medicare.get');
    });

    // Medicare validation management and monitoring
    Route::prefix('medicare-validation')->name('medicare.')->group(function () {

        // Dashboard and reporting
        Route::get('dashboard', [MedicareMacValidationController::class, 'getDashboard'])->name('dashboard');
        Route::post('daily-monitoring', [MedicareMacValidationController::class, 'runDailyMonitoring'])->name('daily_monitoring');

        // Specialty-based validation groupings
        Route::prefix('specialty')->name('specialty.')->group(function () {
            // Vascular Surgery Specialty
            Route::prefix('vascular-surgery')->name('vascular_surgery.')->group(function () {
                Route::get('/', [MedicareMacValidationController::class, 'getVascularSurgeryValidations'])->name('index');
                Route::get('dashboard', [MedicareMacValidationController::class, 'getVascularSurgeryDashboard'])->name('dashboard');
                Route::get('compliance-report', [MedicareMacValidationController::class, 'getVascularSurgeryCompliance'])->name('compliance');
            });

            // Interventional Radiology Specialty
            Route::prefix('interventional-radiology')->name('interventional_radiology.')->group(function () {
                Route::get('/', [MedicareMacValidationController::class, 'getInterventionalRadiologyValidations'])->name('index');
                Route::get('dashboard', [MedicareMacValidationController::class, 'getInterventionalRadiologyDashboard'])->name('dashboard');
            });

            // Cardiology Specialty
            Route::prefix('cardiology')->name('cardiology.')->group(function () {
                Route::get('/', [MedicareMacValidationController::class, 'getCardiologyValidations'])->name('index');
                Route::get('dashboard', [MedicareMacValidationController::class, 'getCardiologyDashboard'])->name('dashboard');
            });

            // Wound Care Specialty
            Route::prefix('wound-care')->name('wound_care.')->group(function () {
                Route::get('/', [MedicareMacValidationController::class, 'getWoundCareValidations'])->name('index');
                Route::get('dashboard', [MedicareMacValidationController::class, 'getWoundCareOnlyDashboard'])->name('dashboard');
            });
        });

        // Validation type groupings (legacy support)
        Route::prefix('type')->name('type.')->group(function () {
            Route::get('vascular-group', [MedicareMacValidationController::class, 'getVascularGroupValidations'])->name('vascular_group');
            Route::get('wound-care-only', [MedicareMacValidationController::class, 'getWoundCareValidations'])->name('wound_care_only');
            Route::get('vascular-only', [MedicareMacValidationController::class, 'getVascularOnlyValidations'])->name('vascular_only');
        });

        // MAC Contractor specific routes
        Route::prefix('mac-contractor')->name('mac.')->group(function () {
            Route::get('novitas', [MedicareMacValidationController::class, 'getNovitasValidations'])->name('novitas');
            Route::get('cgs', [MedicareMacValidationController::class, 'getCgsValidations'])->name('cgs');
            Route::get('palmetto', [MedicareMacValidationController::class, 'getPalmettoValidations'])->name('palmetto');
            Route::get('wisconsin-physicians', [MedicareMacValidationController::class, 'getWisconsinPhysiciansValidations'])->name('wisconsin_physicians');
            Route::get('noridian', [MedicareMacValidationController::class, 'getNoridianValidations'])->name('noridian');
        });

        // Individual validation management
        Route::prefix('{validation_id}')->group(function () {
            Route::patch('monitoring', [MedicareMacValidationController::class, 'toggleMonitoring'])->name('toggle_monitoring');
            Route::get('audit', [MedicareMacValidationController::class, 'getAuditTrail'])->name('audit');
            Route::post('revalidate', [MedicareMacValidationController::class, 'revalidate'])->name('revalidate');
            Route::get('compliance-details', [MedicareMacValidationController::class, 'getComplianceDetails'])->name('compliance_details');
        });

        // Bulk operations
        Route::prefix('bulk')->name('bulk.')->group(function () {
            Route::post('validate', [MedicareMacValidationController::class, 'bulkValidate'])->name('validate');
            Route::post('enable-monitoring', [MedicareMacValidationController::class, 'bulkEnableMonitoring'])->name('enable_monitoring');
            Route::post('disable-monitoring', [MedicareMacValidationController::class, 'bulkDisableMonitoring'])->name('disable_monitoring');
        });

        // Reports and analytics
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('compliance-summary', [MedicareMacValidationController::class, 'getComplianceSummaryReport'])->name('compliance_summary');
            Route::get('reimbursement-risk', [MedicareMacValidationController::class, 'getReimbursementRiskReport'])->name('reimbursement_risk');
            Route::get('specialty-performance', [MedicareMacValidationController::class, 'getSpecialtyPerformanceReport'])->name('specialty_performance');
            Route::get('mac-contractor-analysis', [MedicareMacValidationController::class, 'getMacContractorAnalysis'])->name('mac_contractor_analysis');
            Route::get('validation-trends', [MedicareMacValidationController::class, 'getValidationTrends'])->name('validation_trends');
        });

        // Frontend validation endpoints
        Route::post('quick-check', [MedicareMacValidationController::class, 'quickCheck'])->name('quick_check');
        Route::post('thorough-validate', [MedicareMacValidationController::class, 'thoroughValidate'])->name('thorough_validate');
    });
});

// Episode-centric workflow - moved to web.php for proper CSRF handling

// Quick Request API routes
Route::prefix('v1/quick-request')->middleware(['auth:sanctum'])->group(function () {
    // Episode endpoints
    Route::post('/episodes', [App\Http\Controllers\Api\V1\QuickRequestEpisodeController::class, 'store'])
        ->name('api.quickrequest.episodes.store');
    Route::get('/episodes/{episode}', [App\Http\Controllers\Api\V1\QuickRequestEpisodeController::class, 'show'])
        ->name('api.quickrequest.episodes.show');
    Route::post('/episodes/{episode}/approve', [App\Http\Controllers\Api\V1\QuickRequestEpisodeController::class, 'approve'])
        ->name('api.quickrequest.episodes.approve');

    // Order endpoints
    Route::get('/episodes/{episode}/orders', [App\Http\Controllers\Api\V1\QuickRequestOrderController::class, 'index'])
        ->name('api.quickrequest.orders.index');
    Route::post('/episodes/{episode}/orders', [App\Http\Controllers\Api\V1\QuickRequestOrderController::class, 'store'])
        ->name('api.quickrequest.orders.store');
    Route::get('/orders/{order}', [App\Http\Controllers\Api\V1\QuickRequestOrderController::class, 'show'])
        ->name('api.quickrequest.orders.show');
    Route::patch('/orders/{order}/status', [App\Http\Controllers\Api\V1\QuickRequestOrderController::class, 'updateStatus'])
        ->name('api.quickrequest.orders.updateStatus');

    // Docuseal endpoints moved to Quick Request group below

    // AI Field Mapping Test
    Route::post('/test-ai-mapping', [App\Http\Controllers\Api\V1\QuickRequestController::class, 'testAIFieldMapping'])
        ->name('api.quickrequest.test-ai-mapping');
});

// Order Form Processing routes (Optional Step 8)
Route::prefix('v1/order-form')->middleware(['auth:sanctum'])->group(function () {
    Route::post('process-completion', [App\Http\Controllers\Api\OrderFormController::class, 'processCompletion'])
        ->name('api.order_form.process_completion');
    Route::get('status', [App\Http\Controllers\Api\OrderFormController::class, 'getStatus'])
        ->name('api.order_form.status');
});

// Quick Request Order Summary routes
Route::prefix('v1/quick-request')->middleware(['web'])->group(function () {
    Route::get('order-summary/{order_id}', [App\Http\Controllers\QuickRequestController::class, 'showOrderSummary']);
    Route::get('order-status/{order_id}', [App\Http\Controllers\QuickRequestController::class, 'getOrderStatus']);
    Route::post('create-draft-episode', [App\Http\Controllers\QuickRequestController::class, 'createDraftEpisode']);
    Route::post('create-ivr-submission', [App\Http\Controllers\QuickRequestController::class, 'createIvrSubmission']);
});

// Order Review API routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('orders')->group(function () {
        // Order review and submission
        Route::get('{orderId}/review', [OrderReviewController::class, 'getOrderReview'])
            ->middleware('filter.financial')
            ->name('api.orders.review');
        Route::post('{orderId}/validate', [OrderReviewController::class, 'validateOrder'])
            ->name('api.orders.validate');
        Route::post('{orderId}/submit', [OrderReviewController::class, 'submitOrder'])
            ->name('api.orders.submit');
        Route::post('{orderId}/notes', [OrderReviewController::class, 'addNote'])
            ->name('api.orders.notes');

        // Document viewing
        Route::get('{orderId}/documents/{documentId}', [OrderReviewController::class, 'viewDocument'])
            ->name('api.orders.documents.view');
    });

    // Manufacturer API routes
    Route::prefix('manufacturers')->group(function () {
        Route::get('/', [ManufacturerController::class, 'index'])
            ->name('api.manufacturers.index');
        Route::get('/{manufacturerIdOrName}', [ManufacturerController::class, 'show'])
            ->name('api.manufacturers.show');
        Route::post('/clear-cache', [ManufacturerController::class, 'clearCache'])
            ->name('api.manufacturers.clear-cache');
    });

    // Product API routes
    Route::prefix('products')->middleware('filter.financial')->group(function () {
        Route::get('/with-sizes', [\App\Http\Controllers\Api\ProductDataController::class, 'getProductsWithSizes'])
            ->name('api.products.with-sizes');
        Route::get('/{productId}/sizes', [\App\Http\Controllers\Api\ProductDataController::class, 'getProductWithSizes'])
            ->name('api.products.sizes');
    });
});

// MAC Validation & Eligibility Routes (Public with Rate Limiting)
Route::prefix('v1')->middleware(['throttle:public', 'api.rate.limit:public'])->group(function () {
    // MAC Validation Routes
    Route::post('mac-validation/quick-check', [MedicareMacValidationController::class, 'quickCheck'])->name('mac-validation.quick-check');
    Route::post('mac-validation/thorough-validate', [MedicareMacValidationController::class, 'thoroughValidate'])->name('mac-validation.thorough-validate');

    // Eligibility Check Routes
    Route::post('eligibility/check', [EligibilityController::class, 'checkGeneralEligibility'])->name('eligibility.check_general');
    Route::post('product-requests/{productRequest}/eligibility-check', [EligibilityController::class, 'checkEligibility'])->name('eligibility.check');
    Route::get('product-requests/{productRequest}/eligibility', [EligibilityController::class, 'getEligibility'])->name('eligibility.get');
});

// Clinical Opportunities Engine Routes
Route::prefix('v1')->group(function () {
    Route::prefix('manufacturers')->group(function () {
        Route::get('/', [ManufacturerController::class, 'index'])
            ->name('api.manufacturers.index');
        Route::get('/{manufacturerIdOrName}', [ManufacturerController::class, 'show'])
            ->name('api.manufacturers.show');
        Route::post('/clear-cache', [ManufacturerController::class, 'clearCache'])
            ->name('api.manufacturers.clear-cache');
    });

    Route::prefix('clinical-opportunities')->name('clinical_opportunities.')->group(function () {
        // Scan for opportunities based on clinical data
        Route::post('scan', [ClinicalOpportunitiesController::class, 'scanOpportunities'])->name('scan');

        // Get opportunities for specific conditions/specialties
        Route::get('by-specialty/{specialty}', [ClinicalOpportunitiesController::class, 'getOpportunitiesBySpecialty'])->name('by_specialty');
        Route::get('by-wound-type/{wound_type}', [ClinicalOpportunitiesController::class, 'getOpportunitiesByWoundType'])->name('by_wound_type');

        // Opportunity management
        Route::get('templates', [ClinicalOpportunitiesController::class, 'getOpportunityTemplates'])->name('templates');
        Route::post('validate-opportunity', [ClinicalOpportunitiesController::class, 'validateOpportunity'])->name('validate');

        // Analytics and reporting
        Route::get('analytics/summary', [ClinicalOpportunitiesController::class, 'getAnalyticsSummary'])->name('analytics.summary');
        Route::get('analytics/revenue-impact', [ClinicalOpportunitiesController::class, 'getRevenueImpact'])->name('analytics.revenue');
    });
});

// CMS Coverage API & Validation Builder Routes
Route::prefix('v1')->group(function () {
    Route::prefix('validation-builder')->name('validation_builder.')->group(function () {
        // Validation Rules
        Route::get('rules', [ValidationBuilderController::class, 'getValidationRules'])->name('rules');
        Route::get('user-rules', [ValidationBuilderController::class, 'getUserValidationRules'])->name('user_rules');

        // Order & Product Request Validation
        Route::post('validate-order', [ValidationBuilderController::class, 'validateOrder'])->name('validate_order');
        Route::post('validate-product-request', [ValidationBuilderController::class, 'validateProductRequest'])->name('validate_product_request');

        // Section-specific validation for clinical assessment forms
        Route::post('validate-section', [ValidationBuilderController::class, 'validateSection'])->name('validate_section');

        // CMS Coverage Data
        Route::get('cms-lcds', [ValidationBuilderController::class, 'getCmsLcds'])->name('cms_lcds');
        Route::get('cms-ncds', [ValidationBuilderController::class, 'getCmsNcds'])->name('cms_ncds');
        Route::get('cms-articles', [ValidationBuilderController::class, 'getCmsArticles'])->name('cms_articles');
        Route::get('search-cms', [ValidationBuilderController::class, 'searchCmsDocuments'])->name('search_cms');

        // MAC Information
        Route::get('mac-jurisdiction', [ValidationBuilderController::class, 'getMacJurisdiction'])->name('mac_jurisdiction');

        // Utility Routes
        Route::get('specialties', [ValidationBuilderController::class, 'getAvailableSpecialties'])->name('specialties');
        Route::post('clear-cache', [ValidationBuilderController::class, 'clearSpecialtyCache'])->name('clear_cache');
    });
});

// Public callback endpoint (no auth required for external service callbacks)
Route::post('v1/eligibility/preauth/callback', [EligibilityController::class, 'handleCallback'])->name('eligibility.callback');

// FHIR Server REST API Routes
Route::prefix('fhir')->middleware(['auth:sanctum'])->name('fhir.')->group(function () {
    // CapabilityStatement (public)
    Route::get('metadata', [FhirController::class, 'metadata'])->name('metadata')->withoutMiddleware(['auth:sanctum']);

    // Patient Resource Routes
    Route::prefix('Patient')->name('patient.')->group(function () {
        Route::post('/', [FhirController::class, 'createPatient'])->name('create');
        Route::get('/', [FhirController::class, 'searchPatients'])->name('search');
        Route::get('_history', [FhirController::class, 'patientsHistory'])->name('history_all');
        Route::get('{id}', [FhirController::class, 'readPatient'])->name('read');
        Route::put('{id}', [FhirController::class, 'updatePatient'])->name('update');
        Route::patch('{id}', [FhirController::class, 'patchPatient'])->name('patch');
        Route::delete('{id}', [FhirController::class, 'deletePatient'])->name('delete');
        Route::get('{id}/_history', [FhirController::class, 'patientHistory'])->name('history');
    });

    // Observation Resource Routes
    Route::prefix('Observation')->name('observation.')->group(function () {
        Route::get('/', [FhirController::class, 'searchObservations'])->name('search');
        // Add other Observation specific routes here if needed (e.g., create, read, update)
    });

    // EpisodeOfCare Resource Routes
    Route::prefix('EpisodeOfCare')->name('episodeOfCare.')->group(function () {
        Route::post('/', [FhirController::class, 'createEpisodeOfCare'])->name('create');
        Route::get('/', [FhirController::class, 'searchEpisodeOfCare'])->name('search');
        Route::get('{id}', [FhirController::class, 'readEpisodeOfCare'])->name('read');
        Route::put('{id}', [FhirController::class, 'updateEpisodeOfCare'])->name('update');
        Route::delete('{id}', [FhirController::class, 'deleteEpisodeOfCare'])->name('delete');
    });

    // Coverage Resource Routes
    Route::prefix('Coverage')->name('coverage.')->group(function () {
        Route::post('/', [FhirController::class, 'createCoverage'])->name('create');
    });

    // QuestionnaireResponse Resource Routes
    Route::prefix('QuestionnaireResponse')->name('questionnaireResponse.')->group(function () {
        Route::post('/', [FhirController::class, 'createQuestionnaireResponse'])->name('create');
    });

    // DeviceRequest Resource Routes
    Route::prefix('DeviceRequest')->name('deviceRequest.')->group(function () {
        Route::post('/', [FhirController::class, 'createDeviceRequest'])->name('create');
    });

    // Transaction/Batch endpoint
    Route::post('/', [FhirController::class, 'transaction'])->name('transaction');
});

// Docuseal Integration Routes - DEPRECATED: DocusealController does not exist
// Route::prefix('v1/admin/docuseal')->middleware(['auth:sanctum', 'permission:manage-orders'])->name('docuseal.')->group(function () {
//     // JWT token generation for form embedding
//     Route::post('generate-token', [\App\Http\Controllers\DocusealController::class, 'generateToken'])->name('generate-token');
//
//     // Document generation
//     Route::post('generate-document', [\App\Http\Controllers\DocusealController::class, 'generateDocument'])->name('generate');
//
//     // Submission management
//     Route::get('submissions/{submission_id}/status', [\App\Http\Controllers\DocusealController::class, 'getSubmissionStatus'])->name('status');
//     Route::get('submissions/{submission_id}/download', [\App\Http\Controllers\DocusealController::class, 'downloadDocument'])->name('download');
//
//     // Order submissions
//     Route::get('orders/{order_id}/submissions', [\App\Http\Controllers\DocusealController::class, 'listOrderSubmissions'])->name('order.submissions');
//
//     // Get manufacturer template fields - DEPRECATED: DocusealTemplateController deleted
//     // Route::get('manufacturer/{manufacturer}/fields', [ManufacturerController::class, 'getTemplateFields'])->name('manufacturer.fields');
//
//     // Template Management Routes - DEPRECATED: DocusealTemplateController deleted
//     // Route::get('templates', [\App\Http\Controllers\Api\TemplateMappingController::class, 'getTemplates'])->name('templates.list');
//     // Route::post('sync', [\App\Http\Controllers\Api\TemplateMappingController::class, 'syncTemplates'])->name('templates.sync');
//     // Route::post('test-sync', [\App\Http\Controllers\Api\TemplateMappingController::class, 'testSync'])->name('templates.test-sync');
//
//     // Field Mapping Management Routes
//     Route::get('templates/{id}/field-mappings', [\App\Http\Controllers\Api\TemplateMappingController::class, 'getFieldMappings'])->name('mappings.get');
//     Route::post('templates/{id}/field-mappings', [\App\Http\Controllers\Api\TemplateMappingController::class, 'updateFieldMappings'])->name('mappings.update');
//     Route::post('templates/{id}/field-mappings/bulk', [\App\Http\Controllers\Api\TemplateMappingController::class, 'bulkUpdateMappings'])->name('mappings.bulk');
//     Route::post('templates/{id}/field-mappings/suggest', [\App\Http\Controllers\Api\TemplateMappingController::class, 'suggestMappings'])->name('mappings.suggest');
//     Route::post('templates/{id}/field-mappings/auto-map', [\App\Http\Controllers\Api\TemplateMappingController::class, 'autoMapFields'])->name('mappings.auto-map');
//     Route::post('templates/{id}/field-mappings/validate', [\App\Http\Controllers\Api\TemplateMappingController::class, 'validateMappings'])->name('mappings.validate');
//     Route::get('templates/{id}/mapping-stats', [\App\Http\Controllers\Api\TemplateMappingController::class, 'getMappingStatistics'])->name('mappings.stats');
//
//     // Canonical Fields Routes
//     Route::get('canonical-fields', [\App\Http\Controllers\Api\TemplateMappingController::class, 'getCanonicalFields'])->name('canonical.fields');
//
//     // Import/Export Routes
//     Route::post('field-mappings/import', [\App\Http\Controllers\Api\TemplateMappingController::class, 'importMappings'])->name('mappings.import');
//     Route::get('field-mappings/export/{templateId}', [\App\Http\Controllers\Api\TemplateMappingController::class, 'exportMappings'])->name('mappings.export');
// });

// Field Mapping Management Routes (moved outside deprecated Docuseal group)
Route::prefix('v1/admin/docuseal')->middleware(['auth:sanctum', 'permission:manage-orders'])->name('docuseal.')->group(function () {
    // Field Mapping Management Routes - COMMENTED OUT - TemplateMappingController doesn't exist
    // Route::get('templates/{id}/field-mappings', [\App\Http\Controllers\Api\TemplateMappingController::class, 'getFieldMappings'])->name('mappings.get');
    // Route::post('templates/{id}/field-mappings', [\App\Http\Controllers\Api\TemplateMappingController::class, 'updateFieldMappings'])->name('mappings.update');
    // Route::post('templates/{id}/field-mappings/bulk', [\App\Http\Controllers\Api\TemplateMappingController::class, 'bulkUpdateMappings'])->name('mappings.bulk');
    // Route::post('templates/{id}/field-mappings/suggest', [\App\Http\Controllers\Api\TemplateMappingController::class, 'suggestMappings'])->name('mappings.suggest');
    // Route::post('templates/{id}/field-mappings/auto-map', [\App\Http\Controllers\Api\TemplateMappingController::class, 'autoMapFields'])->name('mappings.auto-map');
    // Route::post('templates/{id}/field-mappings/validate', [\App\Http\Controllers\Api\TemplateMappingController::class, 'validateMappings'])->name('mappings.validate'); 
    // Route::get('templates/{id}/mapping-stats', [\App\Http\Controllers\Api\TemplateMappingController::class, 'getMappingStatistics'])->name('mappings.stats');

    // Canonical Fields Routes - COMMENTED OUT - TemplateMappingController doesn't exist
    // Route::get('canonical-fields', [\App\Http\Controllers\Api\TemplateMappingController::class, 'getCanonicalFields'])->name('canonical.fields');

    // Import/Export Routes - COMMENTED OUT - TemplateMappingController doesn't exist
    // Route::post('field-mappings/import', [\App\Http\Controllers\Api\TemplateMappingController::class, 'importMappings'])->name('mappings.import');
    // Route::get('field-mappings/export/{templateId}', [\App\Http\Controllers\Api\TemplateMappingController::class, 'exportMappings'])->name('mappings.export');
});

// Docuseal Webhook with signature verification
Route::post('v1/webhooks/docuseal', [\App\Http\Controllers\DocusealWebhookController::class, 'handle'])
    ->middleware('webhook.verify:docuseal')
    ->name('docuseal.webhook');
Route::post('v1/webhooks/docuseal/quick-request', [\App\Http\Controllers\QuickRequestController::class, 'handleDocusealWebhook'])
    ->middleware('webhook.verify:docuseal')
    ->name('docuseal.webhook.quickrequest');

// Document Intelligence Routes
Route::prefix('v1/document-intelligence')->middleware(['auth:sanctum', 'permission:manage-orders'])->name('document-intelligence.')->group(function () {
    Route::post('analyze-template', [\App\Http\Controllers\Api\DocumentIntelligenceController::class, 'analyzeTemplate'])->name('analyze-template');
    Route::post('extract-form-data', [\App\Http\Controllers\Api\DocumentIntelligenceController::class, 'extractFormData'])->name('extract-form-data');
    Route::post('get-suggestions', [\App\Http\Controllers\Api\DocumentIntelligenceController::class, 'getSuggestions'])->name('get-suggestions');
    Route::post('batch-analyze', [\App\Http\Controllers\Api\DocumentIntelligenceController::class, 'batchAnalyze'])->name('batch-analyze');
    Route::post('test-mappings', [\App\Http\Controllers\Api\DocumentIntelligenceController::class, 'testMappings'])->name('test-mappings');
});

// Field mapping functionality is now handled by TemplateMappingController

// Unified Docuseal Service Routes - DEPRECATED: DocusealController does not exist
// Route::prefix('v1/docuseal')->middleware(['auth:sanctum'])->name('docuseal.unified.')->group(function () {
//     // Submission management using unified service
//     Route::post('submission/create', [\App\Http\Controllers\Api\DocusealController::class, 'createOrUpdateSubmission'])->name('create-submission');
//     Route::get('submission/{submissionId}', [\App\Http\Controllers\Api\DocusealController::class, 'getSubmission'])->name('get-submission');
//     Route::post('submission/{submissionId}/send', [\App\Http\Controllers\Api\DocusealController::class, 'sendForSigning'])->name('send-signing');
//     Route::get('submission/{submissionId}/download', [\App\Http\Controllers\Api\DocusealController::class, 'downloadDocument'])->name('download-document');
//
//     // Template management
//     Route::get('template/{manufacturer}/fields', [\App\Http\Controllers\Api\DocusealController::class, 'getTemplateFields'])->name('template-fields');
//
//     // Batch operations
//     Route::post('batch-process', [\App\Http\Controllers\Api\DocusealController::class, 'batchProcessEpisodes'])->name('batch-process');
//
//     // Analytics and status
//     Route::get('episodes/status/{status}', [\App\Http\Controllers\Api\DocusealController::class, 'getEpisodesByStatus'])->name('episodes-by-status');
//     Route::get('analytics', [\App\Http\Controllers\Api\DocusealController::class, 'getAnalytics'])->name('analytics');
// });

// Note: eClinicalWorks Integration Routes have been removed
// The EcwController has been deprecated and removed from the codebase

// Commission Management Routes
Route::middleware(['permission:view-commissions'])->group(function () {
    Route::get('/commissions', [CommissionController::class, 'index']);
    Route::get('/commissions/{commission}', [CommissionController::class, 'show']);
});

Route::middleware(['permission:create-commissions'])->group(function () {
    Route::post('/commissions', [CommissionController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:edit-commissions'])->group(function () {
    Route::put('/commissions/{commission}', [CommissionController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'permission:delete-commissions'])->group(function () {
    Route::delete('/commissions/{commission}', [CommissionController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:approve-commissions'])->group(function () {
    Route::post('/commissions/{commission}/approve', [CommissionController::class, 'approve']);
});

Route::middleware(['auth:sanctum', 'permission:process-commissions'])->group(function () {
    Route::post('/commissions/{commission}/process', [CommissionController::class, 'process']);
});

// Sales Rep Management Routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Sales Rep Management (Admin)
    Route::prefix('sales-reps')->middleware(['permission:sales_reps.view'])->group(function () {
        Route::get('/', [SalesRepController::class, 'index'])->name('api.sales-reps.index');
        Route::get('/{id}', [SalesRepController::class, 'show'])->name('api.sales-reps.show');
        Route::get('/{id}/provider-assignments', [SalesRepController::class, 'providerAssignments'])->name('api.sales-reps.provider-assignments');
        Route::get('/{id}/facility-assignments', [SalesRepController::class, 'facilityAssignments'])->name('api.sales-reps.facility-assignments');
        Route::get('/{id}/performance', [SalesRepController::class, 'performance'])->name('api.sales-reps.performance');
    });

    Route::prefix('sales-reps')->middleware(['permission:sales_reps.create'])->group(function () {
        Route::post('/', [SalesRepController::class, 'store'])->name('api.sales-reps.store');
    });

    Route::prefix('sales-reps')->middleware(['permission:sales_reps.edit'])->group(function () {
        Route::put('/{id}', [SalesRepController::class, 'update'])->name('api.sales-reps.update');
    });

    Route::prefix('sales-reps')->middleware(['permission:sales_reps.delete'])->group(function () {
        Route::delete('/{id}', [SalesRepController::class, 'destroy'])->name('api.sales-reps.destroy');
    });

    // Sales Rep Commission Routes (Own data access)
    Route::prefix('commissions/sales-rep')->middleware(['permission:commissions.view_own'])->group(function () {
        Route::get('/summary', [SalesRepCommissionController::class, 'summary'])->name('api.commissions.sales-rep.summary');
        Route::get('/details', [SalesRepCommissionController::class, 'details'])->name('api.commissions.sales-rep.details');
        Route::get('/analytics', [SalesRepCommissionController::class, 'analytics'])->name('api.commissions.sales-rep.analytics');
        Route::get('/payouts', [SalesRepCommissionController::class, 'payouts'])->name('api.commissions.sales-rep.payouts');
        Route::get('/payouts/{payoutId}/statement', [SalesRepCommissionController::class, 'downloadStatement'])->name('api.commissions.sales-rep.statement');
    });
});

// Health check route (secured)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    })->name('api.health');
});

// Role and Permission Management Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Role Management Routes with unique names
    Route::middleware(['auth:sanctum', 'permission:view-roles'])->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('api.roles.index');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('api.roles.show');
        Route::get('/roles/validation/rules', [RoleController::class, 'getValidationRules'])->name('api.roles.validation-rules');
    });

    Route::middleware(['auth:sanctum', 'permission:create-roles'])->group(function () {
        Route::post('/roles', [RoleController::class, 'store'])->name('api.roles.store');
    });

    Route::middleware(['auth:sanctum', 'permission:edit-roles'])->group(function () {
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('api.roles.update');
    });

    Route::middleware(['auth:sanctum', 'permission:delete-roles'])->group(function () {
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('api.roles.destroy');
    });

    // Permission Management Routes with unique names
    Route::apiResource('permissions', PermissionController::class)->names([
        'index' => 'api.permissions.index',
        'store' => 'api.permissions.store',
        'show' => 'api.permissions.show',
        'update' => 'api.permissions.update',
        'destroy' => 'api.permissions.destroy'
    ]);

    // User Role Management with unique names
    Route::post('users/{user}/roles', [\App\Http\Controllers\Admin\UsersController::class, 'assignRoles'])->name('api.users.roles.assign');
    Route::delete('users/{user}/roles/{role}', [\App\Http\Controllers\Admin\UsersController::class, 'removeRole'])->name('api.users.roles.remove');
    Route::put('users/{user}/roles', [\App\Http\Controllers\Admin\UsersController::class, 'syncRoles'])->name('api.users.roles.sync');

    // RBAC Management Routes with unique names
    Route::middleware(['auth:sanctum', 'role:msc-admin'])->group(function () {
        Route::get('/rbac', [RBACController::class, 'index'])->name('api.rbac.index');
        Route::get('/rbac/security-audit', [RBACController::class, 'getSecurityAudit'])->name('api.rbac.security-audit');
        Route::get('/rbac/stats', [RBACController::class, 'getSystemStats'])->name('api.rbac.stats');
        Route::post('/rbac/roles/{role}/toggle-status', [RBACController::class, 'toggleRoleStatus'])->name('api.rbac.roles.toggle-status');
        Route::get('/rbac/roles/{role}/permissions', [RBACController::class, 'getRolePermissions'])->name('api.rbac.roles.permissions');
        Route::put('/rbac/roles/{role}/permissions', [RBACController::class, 'updateRolePermissions'])->name('api.rbac.roles.update-permissions');
    });

    // Deprecated Access Control and Access Request routes removed
    // These features have been deprecated and functionality consolidated into RBAC system
});

// Configuration Routes for QuickRequest
Route::prefix('v1/configuration')->middleware(['auth:sanctum'])->group(function () {
    Route::get('insurance-product-rules', [\App\Http\Controllers\ConfigurationController::class, 'getInsuranceProductRules']);
    Route::get('diagnosis-codes', [\App\Http\Controllers\ConfigurationController::class, 'getDiagnosisCodes']);
    Route::get('wound-types', [\App\Http\Controllers\ConfigurationController::class, 'getWoundTypes']);
    Route::get('product-mue-limits', [\App\Http\Controllers\ConfigurationController::class, 'getProductMueLimits']);
    Route::get('msc-contacts', [\App\Http\Controllers\ConfigurationController::class, 'getMscContacts']);
    Route::get('quick-request', [\App\Http\Controllers\ConfigurationController::class, 'getQuickRequestConfig']);
});

// Provider Profile Management Routes
Route::prefix('v1')->group(function () {
    Route::prefix('providers/{provider_id}')->name('providers.')->group(function () {
        // Profile management
        Route::get('profile', [\App\Http\Controllers\Api\ProviderProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [\App\Http\Controllers\Api\ProviderProfileController::class, 'update'])->name('profile.update');
        Route::get('profile/completion-status', [\App\Http\Controllers\Api\ProviderProfileController::class, 'completionStatus'])->name('profile.completion');

        // Preferences management
        Route::put('profile/notification-preferences', [\App\Http\Controllers\Api\ProviderProfileController::class, 'updateNotificationPreferences'])->name('profile.notifications');
        Route::put('profile/practice-preferences', [\App\Http\Controllers\Api\ProviderProfileController::class, 'updatePracticePreferences'])->name('profile.practice');

        // Credential management
        Route::get('credentials', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'index'])->name('credentials.index');
        Route::post('credentials', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'store'])->name('credentials.store');
        Route::get('credentials/{credential_id}', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'show'])->name('credentials.show');
        Route::put('credentials/{credential_id}', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'update'])->name('credentials.update');
        Route::delete('credentials/{credential_id}', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'destroy'])->name('credentials.destroy');

        // Credential verification (admin only)
        Route::post('credentials/{credential_id}/verify', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'verify'])->name('credentials.verify');
        Route::post('credentials/{credential_id}/reject', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'reject'])->name('credentials.reject');
        Route::post('credentials/{credential_id}/suspend', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'suspend'])->name('credentials.suspend');

        // Document management
        Route::post('credentials/{credential_id}/documents', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'uploadDocument'])->name('credentials.documents.upload');
        Route::get('credentials/{credential_id}/documents/{document_id}', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'downloadDocument'])->name('credentials.documents.download');
        Route::delete('credentials/{credential_id}/documents/{document_id}', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'deleteDocument'])->name('credentials.documents.delete');
    });

    // Credential management utilities
    Route::prefix('credentials')->name('credentials.')->group(function () {
        Route::get('types', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'getCredentialTypes'])->name('types');
        Route::get('expiring', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'getExpiringCredentials'])->name('expiring');
        Route::get('expired', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'getExpiredCredentials'])->name('expired');
        Route::get('pending-verification', [\App\Http\Controllers\Api\ProviderCredentialController::class, 'getPendingVerification'])->name('pending');
    });
});

// Provider Product Routes
Route::prefix('v1')->group(function () {
    // Provider product endpoints
    Route::get('providers/{providerId}/onboarded-products', [\App\Http\Controllers\Api\ProviderProductController::class, 'getOnboardedProducts']);
    Route::get('providers/all-products', [\App\Http\Controllers\Api\ProviderProductController::class, 'getAllProvidersProducts']);

    // IVR field mapping endpoints - DEPRECATED: DocusealTemplateController deleted
    // Route::get('ivr/manufacturers/{manufacturer}/fields', [ManufacturerController::class, 'getTemplateFields']);
});

// Deprecated CustomerManagementController routes removed
// Organization management now handled by OrganizationManagementController via web routes

// Provider Self-Service Routes
Route::prefix('api/v1')->group(function () {
    // Public invitation acceptance
    // Route::get('/invitations/verify/{token}', [ProviderOnboardingController::class, 'verifyInvitation']);
    // Route::post('/invitations/accept/{token}', [ProviderOnboardingController::class, 'acceptInvitation']);

    // Authenticated provider routes
    Route::middleware(['auth:sanctum', 'role:provider'])->group(function () {
        // Route::get('/profile', [ProviderProfileController::class, 'show']);
        // Route::put('/profile', [ProviderProfileController::class, 'update']);
        // Route::post('/profile/verify-npi', [ProviderProfileController::class, 'verifyNPI']);
        // Route::post('/profile/credentials', [ProviderProfileController::class, 'addCredential']);
        // Route::post('/profile/documents', [ProviderProfileController::class, 'uploadDocument']); // This might conflict with admin upload or be different
        // Route::get('/profile/onboarding-status', [ProviderProfileController::class, 'getOnboardingStatus']);
    });
});

// Product Request Flow - Patient Information Step (MVP)
Route::post('/v1/product-requests/patient', [ProductRequestPatientController::class, 'store'])->name('api.v1.product-requests.patient.store');

// Product Request Flow - Clinical Assessment Step (MVP)
Route::post('/v1/product-requests/clinical-assessment', [ProductRequestClinicalAssessmentController::class, 'store'])->name('api.v1.product-requests.clinical-assessment.store');

// Organization Management API Routes
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('organizations')->name('api.organizations.')->group(function () {
        // View organizations
        Route::middleware('permission:view-customers')->group(function () {
            Route::get('/', [\App\Http\Controllers\OrganizationsController::class, 'apiIndex'])->name('index');
            Route::get('/stats', [\App\Http\Controllers\OrganizationsController::class, 'apiStats'])->name('stats');
            Route::get('/{id}', [\App\Http\Controllers\OrganizationsController::class, 'apiShow'])->name('show');
        });

        // Manage organizations (Create, Update, Delete)
        Route::middleware('permission:manage-customers')->group(function () {
            Route::post('/', [\App\Http\Controllers\OrganizationsController::class, 'apiStore'])->name('store');
            Route::put('/{id}', [\App\Http\Controllers\OrganizationsController::class, 'apiUpdate'])->name('update');
            Route::delete('/{id}', [\App\Http\Controllers\OrganizationsController::class, 'apiDestroy'])->name('destroy');
        });
    });
});

// Facility Management API Routes
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('facilities')->name('api.facilities.')->group(function () {
        // View facilities
        Route::middleware('permission:view-facilities')->group(function () {
            Route::get('/', [FacilityController::class, 'apiIndex'])->name('index');
            Route::get('/stats', [FacilityController::class, 'apiStats'])->name('stats');
            Route::get('/{id}', [FacilityController::class, 'apiShow'])->name('show');
        });

        // Create facilities
        Route::middleware('permission:create-facilities')->group(function () {
            Route::post('/', [FacilityController::class, 'apiStore'])->name('store');
        });

        // Update facilities
        Route::middleware('permission:edit-facilities')->group(function () {
            Route::put('/{id}', [FacilityController::class, 'apiUpdate'])->name('update');
        });

        // Delete facilities
        Route::middleware('permission:delete-facilities')->group(function () {
            Route::delete('/{id}', [FacilityController::class, 'apiDestroy'])->name('destroy');
        });
    });
});

// Provider Management API Routes
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('providers')->name('api.providers.')->group(function () {
        // View providers
        Route::middleware('permission:view-providers')->group(function () {
            Route::get('/', [\App\Http\Controllers\ProviderController::class, 'apiIndex'])->name('index');
            Route::get('/stats', [\App\Http\Controllers\ProviderController::class, 'apiStats'])->name('stats');
            Route::get('/{id}', [\App\Http\Controllers\ProviderController::class, 'apiShow'])->name('show');
        });

        // Create providers
        Route::middleware('permission:create-providers')->group(function () {
            Route::post('/', [\App\Http\Controllers\ProviderController::class, 'apiStore'])->name('store');
        });

        // Update providers
        Route::middleware('permission:edit-providers')->group(function () {
            Route::put('/{id}', [\App\Http\Controllers\ProviderController::class, 'apiUpdate'])->name('update');
        });

        // Delete providers
        Route::middleware('permission:delete-providers')->group(function () {
            Route::delete('/{id}', [\App\Http\Controllers\ProviderController::class, 'apiDestroy'])->name('destroy');
        });
    });
});

// Facility Management API Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Admin Facility Management
    Route::middleware(['role:msc-admin', 'permission:manage-facilities'])->prefix('admin')->group(function () {
        Route::get('/facilities', [FacilityController::class, 'apiIndex'])->name('api.admin.facilities.index');
        Route::post('/facilities', [FacilityController::class, 'apiStore'])->name('api.admin.facilities.store');
        Route::get('/facilities/{facility}', [FacilityController::class, 'apiShow'])->name('api.admin.facilities.show');
        Route::put('/facilities/{facility}', [FacilityController::class, 'apiUpdate'])->name('api.admin.facilities.update');
        Route::delete('/facilities/{facility}', [FacilityController::class, 'apiDestroy'])->name('api.admin.facilities.destroy');
    });

    // Provider Facility Management
    Route::middleware(['role:provider', 'permission:view-facilities'])->prefix('provider')->group(function () {
        Route::get('/facilities', [FacilityController::class, 'apiProviderIndex'])->name('api.provider.facilities.index');
        Route::get('/facilities/{facility}', [FacilityController::class, 'apiProviderShow'])->name('api.provider.facilities.show');
    });
});

// Provider Management - Facilities and Products
Route::group([], function () {
    // Provider Facility Management
    Route::post('/providers/{provider}/facilities', [ProviderManagementController::class, 'addFacility'])
        ->name('api.providers.facilities.add');
    Route::delete('/providers/{provider}/facilities/{facility}', [ProviderManagementController::class, 'removeFacility'])
        ->name('api.providers.facilities.remove');

    // Provider Product Management
    Route::post('/providers/{provider}/products', [ProviderManagementController::class, 'addProduct'])
        ->name('api.providers.products.add');
    Route::delete('/providers/{provider}/products/{product}', [ProviderManagementController::class, 'removeProduct'])
        ->name('api.providers.products.remove');
});

// Menu API Routes
Route::prefix('menu')->middleware(['auth:sanctum'])->group(function () {
    // Route::get('/', [\App\Http\Controllers\Api\MenuController::class, 'index'])->name('api.menu.index');
    // Route::post('/search', [\App\Http\Controllers\Api\MenuController::class, 'search'])->name('api.menu.search');
    // Route::post('/toggle-favorite', [\App\Http\Controllers\Api\MenuController::class, 'toggleFavorite'])->name('api.menu.toggle-favorite');
    // Route::post('/toggle-visibility', [\App\Http\Controllers\Api\MenuController::class, 'toggleVisibility'])->name('api.menu.toggle-visibility');
    // Route::post('/update-order', [\App\Http\Controllers\Api\MenuController::class, 'updateOrder'])->name('api.menu.update-order');
    // Route::get('/preferences', [\App\Http\Controllers\Api\MenuController::class, 'preferences'])->name('api.menu.preferences');
    // Route::post('/track-click', [\App\Http\Controllers\Api\MenuController::class, 'trackClick'])->name('api.menu.track-click');
    // Route::get('/badges', [\App\Http\Controllers\Api\MenuController::class, 'badges'])->name('api.menu.badges');
});

// Sales Rep Analytics Routes
Route::prefix('sales-reps')->middleware(['auth:sanctum', 'role:msc-rep,msc-subrep,msc-admin'])->group(function () {
    Route::get('/analytics', [\App\Http\Controllers\Api\SalesRepAnalyticsController::class, 'index'])->name('api.sales-reps.analytics');
    Route::get('/analytics/summary', [\App\Http\Controllers\Api\SalesRepAnalyticsController::class, 'summary'])->name('api.sales-reps.analytics.summary');
    Route::get('/analytics/performance', [\App\Http\Controllers\Api\SalesRepAnalyticsController::class, 'performance'])->name('api.sales-reps.analytics.performance');
    Route::get('/analytics/territories', [\App\Http\Controllers\Api\SalesRepAnalyticsController::class, 'territories'])->name('api.sales-reps.analytics.territories');

    // Enhanced Commission Dashboard Routes
    Route::get('/commission/summary', [\App\Http\Controllers\Api\SalesRepCommissionController::class, 'getSummary'])->name('api.sales-reps.commission.summary');
    Route::get('/commission/details', [\App\Http\Controllers\Api\SalesRepCommissionController::class, 'getDetails'])->name('api.sales-reps.commission.details');
    Route::get('/commission/delayed-payments', [\App\Http\Controllers\Api\SalesRepCommissionController::class, 'getDelayedPayments'])->name('api.sales-reps.commission.delayed-payments');
    Route::get('/commission/analytics', [\App\Http\Controllers\Api\SalesRepCommissionController::class, 'getAnalytics'])->name('api.sales-reps.commission.analytics');
});



// CSRF Token endpoint for automatic refresh
Route::middleware(['web'])->group(function () {
    Route::get('/csrf-token', function () {
        return response()->json([
            'token' => csrf_token(),
            'csrf_token' => csrf_token(),
        ]);
    })->name('api.csrf-token');
});

// Insurance Card Processing Routes
Route::middleware(['web'])->group(function () {
    Route::post('/insurance-card/analyze', [\App\Http\Controllers\Api\InsuranceCardController::class, 'analyze'])
        ->middleware('permission:create-product-requests')
        ->name('api.insurance-card.analyze');
    Route::get('/insurance-card/status', [\App\Http\Controllers\Api\InsuranceCardController::class, 'status'])
        ->name('api.insurance-card.status');
    Route::post('/insurance-card/debug', [\App\Http\Controllers\QuickRequestController::class, 'debugInsuranceCard'])
        ->name('api.insurance-card.debug');
});

// Document Processing Routes (OCR and AI-assisted form filling)
Route::middleware(['web'])->group(function () {
    Route::post('/document/analyze', [\App\Http\Controllers\Api\DocumentProcessingController::class, 'analyze'])
        ->middleware('permission:create-product-requests')
        ->name('api.document.analyze');
    
    Route::post('/document/create-episode', [\App\Http\Controllers\Api\DocumentProcessingController::class, 'createEpisodeFromDocument'])
        ->middleware('permission:create-product-requests')
        ->name('api.document.create-episode');

    // AI-enhanced document processing routes
    Route::post('/document/process-with-ai', [\App\Http\Controllers\Api\DocumentProcessingController::class, 'processWithAi'])
        ->middleware('permission:create-product-requests')
        ->name('api.document.process-with-ai');

    Route::post('/document/enhance-quick-request', [\App\Http\Controllers\Api\DocumentProcessingController::class, 'enhanceQuickRequest'])
        ->middleware('permission:create-product-requests')
        ->name('api.document.enhance-quick-request');

    Route::get('/document/ai-service-status', [\App\Http\Controllers\Api\DocumentProcessingController::class, 'aiServiceStatus'])
        ->name('api.document.ai-service-status');
});

// AI Chat Routes  
Route::prefix('v1/ai')->middleware(['web'])->group(function () {
    Route::post('/chat', [\App\Http\Controllers\Api\AiChatController::class, 'chat'])
        ->name('api.ai.chat');
    Route::post('/form-action', [\App\Http\Controllers\Api\AiChatController::class, 'handleFormAction'])
        ->name('api.ai.form-action');
    Route::post('/tool-result', [\App\Http\Controllers\Api\AiChatController::class, 'handleToolResult'])
        ->name('api.ai.tool-result');
    Route::post('/text-to-speech', [\App\Http\Controllers\Api\AiChatController::class, 'textToSpeech'])
        ->name('api.ai.text-to-speech');
    Route::get('/voices', [\App\Http\Controllers\Api\AiChatController::class, 'getVoices'])
        ->name('api.ai.voices');
    
    // Hybrid Voice/Text Mode Routes
    Route::post('/realtime/session', [\App\Http\Controllers\Api\AiChatController::class, 'createRealtimeSession'])
        ->name('api.ai.realtime.session');
    Route::get('/realtime/test', function() {
        $service = new \App\Services\AI\AzureRealtimeService();
        return response()->json($service->createSession(['voice' => 'alloy']));
    })->name('api.ai.realtime.test');
    Route::get('/capabilities', [\App\Http\Controllers\Api\AiChatController::class, 'getCapabilities'])
        ->name('api.ai.capabilities');
    Route::post('/switch-mode', [\App\Http\Controllers\Api\AiChatController::class, 'switchMode'])
        ->name('api.ai.switch-mode');
    
    Route::post('/agent/create-thread', [\App\Http\Controllers\Api\AiChatController::class, 'createThread'])
        ->name('api.ai.agent.create-thread');
    Route::post('/agent/{threadId}/message', [\App\Http\Controllers\Api\AiChatController::class, 'sendMessage'])
        ->name('api.ai.agent.message');
    Route::get('/agent/{threadId}/status', [\App\Http\Controllers\Api\AiChatController::class, 'getThreadStatus'])
        ->name('api.ai.agent.status');
});

// QuickRequest AI Routes
Route::group([], function () {
    Route::post('/quick-request/generate-ivr', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'generateIVR'])
        ->middleware('permission:create-product-requests')
        ->name('api.quick-request.generate-ivr');
    
    Route::post('/quick-request/validate', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'validateFormData'])
        ->middleware('permission:create-product-requests')
        ->name('api.quick-request.validate');
    
    Route::get('/quick-request/user-permissions', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'getUserPermissions'])
        ->name('api.quick-request.user-permissions');
});

// Payer Search Routes
Route::middleware(['web'])->group(function () {
    Route::get('/payers/search', [\App\Http\Controllers\PayerController::class, 'search'])
        ->name('api.payers.search');
});

// Diagnosis codes
Route::get('/diagnosis-codes', [\App\Http\Controllers\Api\DiagnosisCodeController::class, 'getAll']);
Route::post('/diagnosis-codes/by-wound-type', [\App\Http\Controllers\Api\DiagnosisCodeController::class, 'getByWoundType']);

// Quick Request API Routes (for providers)
Route::prefix('v1/quick-request')->middleware(['auth:sanctum'])->group(function () {
    // Get manufacturer template fields for QuickRequest - DEPRECATED: DocusealTemplateController deleted
    // Route::get('manufacturer/{manufacturer}/fields', [ManufacturerController::class, 'getTemplateFields'])
    //     ->middleware('permission:create-product-requests');
// Quick Request backend endpoints
    Route::post('episodes', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'startEpisode'])->middleware('permission:create-product-requests');
    Route::post('episodes/{episode}/follow-up', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'addFollowUp'])->middleware('permission:create-product-requests');
    Route::post('episodes/{episode}/approve', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'approve'])->middleware('permission:manage-episodes');

    // Docuseal Builder Token
    Route::post('docuseal/generate-builder-token', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'generateBuilderToken'])
        ->middleware('permission:create-product-requests');
});

// QuickRequest Episode Creation Route (Enhanced)
Route::middleware(['api', 'auth:sanctum'])->group(function () {
    Route::post('/quick-request/create-episode', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'createEpisode'])
        ->name('api.quick-request.create-episode')
        ->middleware(['permission:create-product-requests', 'handle_quick_request_errors']);

    Route::post('/quick-request/create-episode-with-documents', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'createEpisodeWithDocuments'])
        ->name('api.quick-request.create-episode-with-documents')
        ->middleware(['permission:create-product-requests', 'handle_quick_request_errors']);

    Route::post('/quick-request/extract-ivr-fields', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'extractIvrFields'])
        ->name('api.quick-request.extract-ivr-fields')
        ->middleware(['permission:create-product-requests', 'handle_quick_request_errors']);
});

// Episode MAC Validation Routes
Route::prefix('episodes')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/{episode}/mac-validation', [\App\Http\Controllers\Api\EpisodeMacValidationController::class, 'show'])
        ->name('api.episodes.mac-validation')
        ->middleware('permission:view-orders');
});

// Admin Notification Routes
Route::prefix('admin')->group(function () {
    Route::post('send-notification', [App\Http\Controllers\Api\AdminNotificationController::class, 'sendNotification'])
        ->name('api.admin.send-notification');
});

// Order Status Management
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/order-status/update', [App\Http\Controllers\Api\OrderStatusController::class, 'updateStatus']);
    Route::delete('/order-status/remove-document', [App\Http\Controllers\Api\OrderStatusController::class, 'removeDocument']);
});
// Fallback Route for 404 API requests

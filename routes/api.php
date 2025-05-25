<?php

use App\Http\Controllers\FhirController;
use App\Http\Controllers\EcwController;
use App\Http\Controllers\CommissionRuleController;
use App\Http\Controllers\CommissionRecordController;
use App\Http\Controllers\CommissionPayoutController;
use App\Http\Controllers\Api\EligibilityController;
use App\Http\Controllers\Api\ValidationBuilderController;
use Illuminate\Support\Facades\Route;

// Medicare MAC Validation Routes - Organized by Specialty
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // Order-specific Medicare validation routes
    Route::prefix('orders/{order_id}')->group(function () {
        Route::post('medicare-validation', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'validateOrder'])->name('medicare.validate');
        Route::get('medicare-validation', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getValidation'])->name('medicare.get');
    });

    // Medicare validation management and monitoring
    Route::prefix('medicare-validation')->name('medicare.')->group(function () {

        // Dashboard and reporting
        Route::get('dashboard', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getDashboard'])->name('dashboard');
        Route::post('daily-monitoring', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'runDailyMonitoring'])->name('daily_monitoring');

        // Specialty-based validation groupings
        Route::prefix('specialty')->name('specialty.')->group(function () {
            // Vascular Surgery Specialty
            Route::prefix('vascular-surgery')->name('vascular_surgery.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getVascularSurgeryValidations'])->name('index');
                Route::get('dashboard', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getVascularSurgeryDashboard'])->name('dashboard');
                Route::get('compliance-report', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getVascularSurgeryCompliance'])->name('compliance');
            });

            // Interventional Radiology Specialty
            Route::prefix('interventional-radiology')->name('interventional_radiology.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getInterventionalRadiologyValidations'])->name('index');
                Route::get('dashboard', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getInterventionalRadiologyDashboard'])->name('dashboard');
            });

            // Cardiology Specialty
            Route::prefix('cardiology')->name('cardiology.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getCardiologyValidations'])->name('index');
                Route::get('dashboard', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getCardiologyDashboard'])->name('dashboard');
            });

            // Wound Care Specialty
            Route::prefix('wound-care')->name('wound_care.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getWoundCareValidations'])->name('index');
                Route::get('dashboard', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getWoundCareOnlyDashboard'])->name('dashboard');
            });
        });

        // Validation type groupings (legacy support)
        Route::prefix('type')->name('type.')->group(function () {
            Route::get('vascular-group', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getVascularGroupValidations'])->name('vascular_group');
            Route::get('wound-care-only', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getWoundCareValidations'])->name('wound_care_only');
            Route::get('vascular-only', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getVascularOnlyValidations'])->name('vascular_only');
        });

        // MAC Contractor specific routes
        Route::prefix('mac-contractor')->name('mac.')->group(function () {
            Route::get('novitas', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getNovitasValidations'])->name('novitas');
            Route::get('cgs', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getCgsValidations'])->name('cgs');
            Route::get('palmetto', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getPalmettoValidations'])->name('palmetto');
            Route::get('wisconsin-physicians', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getWisconsinPhysiciansValidations'])->name('wisconsin_physicians');
            Route::get('noridian', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getNoridianValidations'])->name('noridian');
        });

        // Individual validation management
        Route::prefix('{validation_id}')->group(function () {
            Route::patch('monitoring', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'toggleMonitoring'])->name('toggle_monitoring');
            Route::get('audit', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getAuditTrail'])->name('audit');
            Route::post('revalidate', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'revalidate'])->name('revalidate');
            Route::get('compliance-details', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getComplianceDetails'])->name('compliance_details');
        });

        // Bulk operations
        Route::prefix('bulk')->name('bulk.')->group(function () {
            Route::post('validate', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'bulkValidate'])->name('validate');
            Route::post('enable-monitoring', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'bulkEnableMonitoring'])->name('enable_monitoring');
            Route::post('disable-monitoring', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'bulkDisableMonitoring'])->name('disable_monitoring');
        });

        // Reports and analytics
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('compliance-summary', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getComplianceSummaryReport'])->name('compliance_summary');
            Route::get('reimbursement-risk', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getReimbursementRiskReport'])->name('reimbursement_risk');
            Route::get('specialty-performance', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getSpecialtyPerformanceReport'])->name('specialty_performance');
            Route::get('mac-contractor-analysis', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getMacContractorAnalysis'])->name('mac_contractor_analysis');
            Route::get('validation-trends', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getValidationTrends'])->name('validation_trends');
        });
    });
});

// Eligibility & Pre-Authorization Routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Order-specific eligibility routes
    Route::post('orders/{order_id}/eligibility-check', [EligibilityController::class, 'checkEligibility'])->name('eligibility.check');
    Route::get('orders/{order_id}/eligibility', [EligibilityController::class, 'getEligibility'])->name('eligibility.get');
    Route::post('orders/{order_id}/preauth', [EligibilityController::class, 'requestPreAuth'])->name('preauth.request');
    Route::get('orders/{order_id}/preauth/tasks', [EligibilityController::class, 'getPreAuthTasks'])->name('preauth.tasks');

    // Eligibility summary and management
    Route::get('eligibility/summary', [EligibilityController::class, 'getSummary'])->name('eligibility.summary');
    Route::get('eligibility/health-check', [EligibilityController::class, 'healthCheck'])->name('eligibility.health');
});

// CMS Coverage API & Validation Builder Routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('validation-builder')->name('validation_builder.')->group(function () {
        // Validation Rules
        Route::get('rules', [ValidationBuilderController::class, 'getValidationRules'])->name('rules');
        Route::get('user-rules', [ValidationBuilderController::class, 'getUserValidationRules'])->name('user_rules');

        // Order & Product Request Validation
        Route::post('validate-order', [ValidationBuilderController::class, 'validateOrder'])->name('validate_order');
        Route::post('validate-product-request', [ValidationBuilderController::class, 'validateProductRequest'])->name('validate_product_request');

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
Route::prefix('fhir')->name('fhir.')->group(function () {
    // CapabilityStatement
    Route::get('metadata', [FhirController::class, 'metadata'])->name('metadata');

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

    // Transaction/Batch endpoint
    Route::post('/', [FhirController::class, 'transaction'])->name('transaction');
});

// eClinicalWorks Integration Routes
Route::prefix('ecw')->name('ecw.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('auth', [EcwController::class, 'authenticate'])->name('auth');
    Route::get('callback', [EcwController::class, 'callback'])->name('callback');
    Route::get('status', [EcwController::class, 'status'])->name('status');
    Route::get('test', [EcwController::class, 'test'])->name('test');
    Route::post('disconnect', [EcwController::class, 'disconnect'])->name('disconnect');

    // Patient data routes
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('search', [EcwController::class, 'searchPatients'])->name('search');
        Route::get('{id}', [EcwController::class, 'getPatient'])->name('read');
        Route::get('{id}/observations', [EcwController::class, 'getPatientObservations'])->name('observations');
        Route::get('{id}/documents', [EcwController::class, 'getPatientDocuments'])->name('documents');
        Route::get('{id}/conditions', [EcwController::class, 'getPatientConditions'])->name('conditions');
        Route::post('{id}/order-summary', [EcwController::class, 'createOrderSummary'])->name('order-summary.create');
    });
});

// JWK endpoint for eCW integration (public endpoint)
Route::get('.well-known/jwks.json', [EcwController::class, 'jwks'])->name('jwks');

// Commission Management Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Commission Rules
    Route::apiResource('commission-rules', CommissionRuleController::class);

    // Commission Records
    Route::get('commission-records', [CommissionRecordController::class, 'index']);
    Route::get('commission-records/{record}', [CommissionRecordController::class, 'show']);
    Route::post('commission-records/{record}/approve', [CommissionRecordController::class, 'approve']);
    Route::get('commission-records/summary', [CommissionRecordController::class, 'summary']);

    // Commission Payouts
    Route::get('commission-payouts', [CommissionPayoutController::class, 'index']);
    Route::post('commission-payouts/generate', [CommissionPayoutController::class, 'generate']);
    Route::get('commission-payouts/{payout}', [CommissionPayoutController::class, 'show']);
    Route::post('commission-payouts/{payout}/approve', [CommissionPayoutController::class, 'approve']);
    Route::post('commission-payouts/{payout}/process', [CommissionPayoutController::class, 'process']);
});

// Health check route (secured)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ]);
    })->name('api.health');
});

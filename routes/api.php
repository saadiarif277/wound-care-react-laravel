<?php

use App\Http\Controllers\FhirController;
use App\Http\Controllers\EcwController;
use App\Http\Controllers\CommissionRuleController;
use App\Http\Controllers\CommissionRecordController;
use App\Http\Controllers\CommissionPayoutController;
use App\Http\Controllers\Api\EligibilityController;
use App\Http\Controllers\Api\ValidationBuilderController;
use App\Http\Controllers\Api\ClinicalOpportunitiesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Commission\CommissionController;
use App\Http\Controllers\RBACController;
// Deprecated controllers removed - AccessControlController, CustomerManagementController
use App\Http\Controllers\Api\V1\ProviderOnboardingController;
use App\Http\Controllers\Api\V1\ProviderProfileController;
use App\Http\Controllers\Api\ProductRequestPatientController;
use App\Http\Controllers\Api\ProductRequestClinicalAssessmentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\Api\MedicareMacValidationController;
use App\Http\Controllers\Admin\ProviderManagementController;

// Medicare MAC Validation Routes - Organized by Specialty
Route::prefix('v1')->group(function () {

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

        // Frontend validation endpoints
        Route::post('quick-check', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'quickCheck'])->name('quick_check');
        Route::post('thorough-validate', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'thoroughValidate'])->name('thorough_validate');
    });
});

// MAC Validation & Eligibility Routes (Public)
Route::prefix('v1')->group(function () {
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

    // Observation Resource Routes
    Route::prefix('Observation')->name('observation.')->group(function () {
        Route::get('/', [FhirController::class, 'searchObservations'])->name('search');
        // Add other Observation specific routes here if needed (e.g., create, read, update)
    });

    // Transaction/Batch endpoint
    Route::post('/', [FhirController::class, 'transaction'])->name('transaction');
});

// DocuSeal Integration Routes
Route::prefix('v1/admin/docuseal')->middleware(['permission:manage-orders'])->name('docuseal.')->group(function () {
    // JWT token generation for form embedding
    Route::post('generate-token', [\App\Http\Controllers\DocusealController::class, 'generateToken'])->name('generate-token');

    // Document generation
    Route::post('generate-document', [\App\Http\Controllers\DocusealController::class, 'generateDocument'])->name('generate');

    // Submission management
    Route::get('submissions/{submission_id}/status', [\App\Http\Controllers\DocusealController::class, 'getSubmissionStatus'])->name('status');
    Route::get('submissions/{submission_id}/download', [\App\Http\Controllers\DocusealController::class, 'downloadDocument'])->name('download');

    // Order submissions
    Route::get('orders/{order_id}/submissions', [\App\Http\Controllers\DocusealController::class, 'listOrderSubmissions'])->name('order.submissions');
    
    // Get manufacturer template fields
    Route::get('manufacturer/{manufacturer}/fields', [\App\Http\Controllers\Api\V1\DocuSealTemplateController::class, 'getManufacturerFields'])->name('manufacturer.fields');
});

// DocuSeal Webhook (no auth required for external webhooks)
Route::post('v1/webhooks/docuseal', [\App\Http\Controllers\DocusealController::class, 'handleWebhook'])->name('docuseal.webhook');
Route::post('v1/webhooks/docuseal/quick-request', [\App\Http\Controllers\QuickRequestController::class, 'handleDocuSealWebhook'])->name('docuseal.webhook.quickrequest');

// eClinicalWorks Integration Routes
Route::prefix('ecw')->name('ecw.')->group(function () {
    Route::get('auth', [EcwController::class, 'authenticate'])->name('auth');
    Route::get('callback', [EcwController::class, 'callback'])->name('callback');
    Route::get('status', [EcwController::class, 'status'])->name('status');
    Route::get('test', [EcwController::class, 'test'])->name('test');

    // Patient data routes
    Route::get('patients', [EcwController::class, 'getPatients'])->name('patients');
    Route::get('patients/{patient_id}', [EcwController::class, 'getPatient'])->name('patient');
    Route::get('patients/{patient_id}/appointments', [EcwController::class, 'getPatientAppointments'])->name('patient.appointments');
    Route::get('patients/{patient_id}/documents', [EcwController::class, 'getPatientDocuments'])->name('patient.documents');

    // Provider data routes
    Route::get('providers', [EcwController::class, 'getProviders'])->name('providers');
    Route::get('providers/{provider_id}', [EcwController::class, 'getProvider'])->name('provider');

    // Appointment routes
    Route::get('appointments', [EcwController::class, 'getAppointments'])->name('appointments');
    Route::post('appointments', [EcwController::class, 'createAppointment'])->name('appointments.create');

    // Document routes
    Route::get('documents', [EcwController::class, 'getDocuments'])->name('documents');
    Route::post('documents', [EcwController::class, 'uploadDocument'])->name('documents.upload');
});

// JWK endpoint for eCW integration (public endpoint)
Route::get('.well-known/jwks.json', [EcwController::class, 'jwks'])->name('jwks');

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
    Route::post('users/{user}/roles', [UserController::class, 'assignRoles'])->name('api.users.roles.assign');
    Route::delete('users/{user}/roles/{role}', [UserController::class, 'removeRole'])->name('api.users.roles.remove');
    Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->name('api.users.roles.sync');

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
            Route::get('/', [\App\Http\Controllers\FacilityController::class, 'apiIndex'])->name('index');
            Route::get('/stats', [\App\Http\Controllers\FacilityController::class, 'apiStats'])->name('stats');
            Route::get('/{id}', [\App\Http\Controllers\FacilityController::class, 'apiShow'])->name('show');
        });

        // Create facilities
        Route::middleware('permission:create-facilities')->group(function () {
            Route::post('/', [\App\Http\Controllers\FacilityController::class, 'apiStore'])->name('store');
        });

        // Update facilities
        Route::middleware('permission:edit-facilities')->group(function () {
            Route::put('/{id}', [\App\Http\Controllers\FacilityController::class, 'apiUpdate'])->name('update');
        });

        // Delete facilities
        Route::middleware('permission:delete-facilities')->group(function () {
            Route::delete('/{id}', [\App\Http\Controllers\FacilityController::class, 'apiDestroy'])->name('destroy');
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
    Route::post('/providers/{provider}/facilities', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'addFacility'])
        ->name('api.providers.facilities.add');
    Route::delete('/providers/{provider}/facilities/{facility}', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'removeFacility'])
        ->name('api.providers.facilities.remove');

    // Provider Product Management
    Route::post('/providers/{provider}/products', [ProviderManagementController::class, 'addProduct'])
        ->name('api.providers.products.add');
    Route::delete('/providers/{provider}/products/{product}', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'removeProduct'])
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
});



// Insurance Card Processing Routes
Route::middleware(['web'])->group(function () {
    Route::post('/insurance-card/analyze', [\App\Http\Controllers\QuickRequestController::class, 'analyzeInsuranceCard'])
        ->name('api.insurance-card.analyze');
    Route::get('/insurance-card/status', [\App\Http\Controllers\QuickRequestController::class, 'checkAzureStatus'])
        ->name('api.insurance-card.status');
    Route::post('/insurance-card/debug', [\App\Http\Controllers\QuickRequestController::class, 'debugInsuranceCard'])
        ->name('api.insurance-card.debug');
});

// Payer Search Routes
Route::middleware(['web'])->group(function () {
    Route::get('/payers/search', [\App\Http\Controllers\PayerController::class, 'search'])
        ->name('api.payers.search');
});

// Quick Request API Routes (for providers)
Route::prefix('v1/quick-request')->middleware(['auth:sanctum'])->group(function () {
    // Get manufacturer template fields for QuickRequest
    Route::get('manufacturer/{manufacturer}/fields', [\App\Http\Controllers\Api\V1\DocuSealTemplateController::class, 'getManufacturerFields'])
        ->middleware('permission:create-product-requests');
});

// Fallback Route for 404 API requests


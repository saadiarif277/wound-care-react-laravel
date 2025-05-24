<?php

use App\Http\Controllers\FhirController;
use App\Http\Controllers\EcwController;
use App\Http\Controllers\CommissionRuleController;
use App\Http\Controllers\CommissionRecordController;
use App\Http\Controllers\CommissionPayoutController;
use App\Http\Controllers\Api\EligibilityController;
use Illuminate\Support\Facades\Route;

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
    // OAuth2 Authentication
    Route::get('auth', [EcwController::class, 'authenticate'])->name('auth');
    Route::get('callback', [EcwController::class, 'callback'])->name('callback');

    // Connection Management
    Route::get('status', [EcwController::class, 'status'])->name('status');
    Route::post('disconnect', [EcwController::class, 'disconnect'])->name('disconnect');
    Route::get('test', [EcwController::class, 'testConnection'])->name('test');

    // FHIR Data Access
    Route::get('patients/search', [EcwController::class, 'searchPatients'])->name('patients.search');
    Route::get('patients/{patient_id}', [EcwController::class, 'getPatient'])->name('patients.show');
    Route::get('patients/{patient_id}/observations', [EcwController::class, 'getPatientObservations'])->name('patients.observations');
    Route::get('patients/{patient_id}/documents', [EcwController::class, 'getPatientDocuments'])->name('patients.documents');
});

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

// Public callback endpoint (no auth required for external service callbacks)
Route::post('v1/eligibility/preauth/callback', [EligibilityController::class, 'handleCallback'])->name('eligibility.callback');

// Medicare MAC Validation Routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Order-specific Medicare validation routes
    Route::post('orders/{order_id}/medicare-validation', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'validateOrder'])->name('medicare.validate');
    Route::get('orders/{order_id}/medicare-validation', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getValidation'])->name('medicare.get');

    // Medicare validation management and monitoring
    Route::get('medicare-validation/vascular-group', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getVascularGroupValidations'])->name('medicare.vascular_group');
    Route::get('medicare-validation/wound-care-only', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getWoundCareValidations'])->name('medicare.wound_care');
    Route::post('medicare-validation/daily-monitoring', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'runDailyMonitoring'])->name('medicare.daily_monitoring');
    Route::get('medicare-validation/dashboard', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getDashboard'])->name('medicare.dashboard');

    // Individual validation management
    Route::patch('medicare-validation/{validation_id}/monitoring', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'toggleMonitoring'])->name('medicare.toggle_monitoring');
    Route::get('medicare-validation/{validation_id}/audit', [\App\Http\Controllers\Api\MedicareMacValidationController::class, 'getAuditTrail'])->name('medicare.audit');
});

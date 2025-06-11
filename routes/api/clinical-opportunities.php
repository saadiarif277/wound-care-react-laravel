<?php

use App\Http\Controllers\Api\V1\ClinicalOpportunityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Clinical Opportunity API Routes
|--------------------------------------------------------------------------
|
| These routes handle all clinical opportunity engine operations including
| identifying opportunities, taking actions, and tracking outcomes.
|
*/

Route::prefix('v1/clinical-opportunities')->middleware(['auth:sanctum'])->group(function () {
    
    // Patient-specific opportunities
    Route::prefix('patients/{patientId}')->group(function () {
        // Get opportunities for a patient
        Route::get('opportunities', [ClinicalOpportunityController::class, 'getOpportunities'])
            ->name('clinical-opportunities.patient.list');
        
        // Get opportunity history for a patient
        Route::get('history', [ClinicalOpportunityController::class, 'getPatientHistory'])
            ->name('clinical-opportunities.patient.history');
    });
    
    // Opportunity-specific actions
    Route::prefix('opportunities/{opportunityId}')->group(function () {
        // Get opportunity details
        Route::get('/', [ClinicalOpportunityController::class, 'getOpportunityDetails'])
            ->name('clinical-opportunities.details');
        
        // Take action on opportunity
        Route::post('actions', [ClinicalOpportunityController::class, 'takeAction'])
            ->name('clinical-opportunities.take-action');
        
        // Dismiss opportunity
        Route::post('dismiss', [ClinicalOpportunityController::class, 'dismiss'])
            ->name('clinical-opportunities.dismiss');
    });
    
    // Dashboard and analytics
    Route::get('dashboard', [ClinicalOpportunityController::class, 'getDashboard'])
        ->name('clinical-opportunities.dashboard');
    
    Route::get('trends', [ClinicalOpportunityController::class, 'getTrends'])
        ->name('clinical-opportunities.trends');
});

// Admin routes for managing rules
Route::prefix('v1/admin/clinical-opportunities')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Manage opportunity rules
    Route::get('rules', [ClinicalOpportunityController::class, 'getRules'])
        ->name('admin.clinical-opportunities.rules.list');
    
    Route::post('rules', [ClinicalOpportunityController::class, 'createRule'])
        ->name('admin.clinical-opportunities.rules.create');
    
    Route::put('rules/{ruleId}', [ClinicalOpportunityController::class, 'updateRule'])
        ->name('admin.clinical-opportunities.rules.update');
    
    Route::delete('rules/{ruleId}', [ClinicalOpportunityController::class, 'deleteRule'])
        ->name('admin.clinical-opportunities.rules.delete');
    
    // Analytics and reporting
    Route::get('analytics/outcomes', [ClinicalOpportunityController::class, 'getOutcomeAnalytics'])
        ->name('admin.clinical-opportunities.analytics.outcomes');
    
    Route::get('analytics/performance', [ClinicalOpportunityController::class, 'getPerformanceMetrics'])
        ->name('admin.clinical-opportunities.analytics.performance');
});
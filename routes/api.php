<?php

use App\Http\Controllers\FhirController;
use App\Http\Controllers\CommissionRuleController;
use App\Http\Controllers\CommissionRecordController;
use App\Http\Controllers\CommissionPayoutController;
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

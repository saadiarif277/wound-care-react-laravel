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

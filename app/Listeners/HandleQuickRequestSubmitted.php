<?php

namespace App\Listeners;

use App\Events\QuickRequestSubmitted;
use Illuminate\Support\Facades\Log;

class HandleQuickRequestSubmitted
{
    /**
     * Handle the event.
     *
     * @param  \App\Events\QuickRequestSubmitted  $event
     * @return void
     */
    public function handle(QuickRequestSubmitted $event)
    {
        // Log the event for monitoring
        Log::info('QuickRequestSubmitted event received', [
            'episode_id' => $event->episode->id,
            'product_request_id' => $event->productRequest->id ?? null,
            'total_amount' => $event->calculation['total'] ?? 0
        ]);
        
        // PDF generation is handled in the QuickRequestController/PDFService workflow
        // Additional background processing can be added here if needed
        
        // Example: Dispatch notifications, trigger analytics, etc.
        // dispatch(new ProcessOrderNotifications($event->productRequest));
    }
}

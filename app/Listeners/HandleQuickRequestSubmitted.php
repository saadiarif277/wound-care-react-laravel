<?php

namespace App\Listeners;

use App\Events\QuickRequestSubmitted;
use App\Jobs\ProcessQuickRequestToDocusealAndFhir;

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
        // Dispatch the background job with the episode ID
        ProcessQuickRequestToDocusealAndFhir::dispatch($event->episode->id);
    }
}

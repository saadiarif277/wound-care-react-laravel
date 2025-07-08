<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Learning\BehavioralEvent;
use Illuminate\Support\Facades\Log;

class ProcessBehavioralEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $eventData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $eventData)
    {
        $this->eventData = $eventData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Store the behavioral event in the database
            BehavioralEvent::create([
                'event_id' => $this->eventData['event_id'],
                'user_id' => $this->eventData['user_id'],
                'user_role' => $this->eventData['user_role'],
                'facility_id' => $this->eventData['facility_id'],
                'organization_id' => $this->eventData['organization_id'],
                'event_type' => $this->eventData['event_type'],
                'event_category' => $this->eventData['event_category'],
                'timestamp' => $this->eventData['timestamp'],
                'session_id' => $this->eventData['session_id'],
                'ip_hash' => $this->eventData['ip_hash'],
                'user_agent_hash' => $this->eventData['user_agent_hash'],
                'url_path' => $this->eventData['url_path'],
                'http_method' => $this->eventData['http_method'],
                'event_data' => $this->eventData['event_data'],
                'context' => $this->eventData['context'],
                'browser_info' => $this->eventData['browser_info'],
                'performance_metrics' => $this->eventData['performance_metrics'],
            ]);

            // Trigger ML processing if enabled
            $this->triggerMLProcessing();

        } catch (\Exception $e) {
            Log::error('Failed to process behavioral event', [
                'event_id' => $this->eventData['event_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            // Don't fail the job, just log the error
            // Behavioral tracking shouldn't break the main application
        }
    }

    /**
     * Trigger ML processing for real-time learning
     */
    private function triggerMLProcessing(): void
    {
        // This could trigger additional processing like:
        // - Feature extraction for ML models
        // - Real-time recommendation updates
        // - Pattern detection algorithms
        
        // For now, just log that ML processing could be triggered here
        Log::debug('ML processing trigger point', [
            'event_type' => $this->eventData['event_type'],
            'user_id' => $this->eventData['user_id']
        ]);
    }
} 
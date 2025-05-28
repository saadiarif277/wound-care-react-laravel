<?php

namespace App\Jobs;

use App\Models\PreAuthorization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubmitPreAuthorizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $preAuthorizationId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $preAuth = PreAuthorization::find($this->preAuthorizationId);
        
        if (!$preAuth) {
            Log::error('PreAuthorization not found for job', [
                'pre_auth_id' => $this->preAuthorizationId
            ]);
            return;
        }

        try {
            $this->submitToPayerSystem($preAuth);
            
            Log::info('Pre-authorization submitted successfully via job', [
                'pre_auth_id' => $preAuth->id,
                'authorization_number' => $preAuth->authorization_number
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to submit pre-authorization via job', [
                'pre_auth_id' => $preAuth->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Update status on final failure
            if ($this->attempts() >= $this->tries) {
                $preAuth->update([
                    'status' => 'submission_failed',
                    'payer_response' => ['error' => $e->getMessage()],
                ]);
            }

            throw $e; // Re-throw to trigger retry logic
        }
    }

    /**
     * Submit to payer system with proper error handling
     */
    private function submitToPayerSystem(PreAuthorization $preAuth): void
    {
        // Use environment-specific endpoint
        $endpoint = config('payers.submission_endpoint');
        
        if (!$endpoint) {
            throw new \Exception('Payer submission endpoint not configured');
        }

        $response = Http::timeout(60)->post($endpoint, [
            'authorization_number' => $preAuth->authorization_number,
            'payer_name' => $preAuth->payer_name,
            'patient_id' => $preAuth->patient_id,
            'diagnosis_codes' => $preAuth->diagnosis_codes,
            'procedure_codes' => $preAuth->procedure_codes,
            'clinical_documentation' => $preAuth->clinical_documentation,
            'urgency' => $preAuth->urgency,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Payer system responded with error: ' . $response->status());
        }

        $responseData = $response->json();

        $preAuth->update([
            'payer_transaction_id' => $responseData['transaction_id'] ?? null,
            'payer_confirmation' => $responseData['confirmation_number'] ?? null,
            'status' => 'processing',
            'payer_response' => $responseData,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Pre-authorization job failed permanently', [
            'pre_auth_id' => $this->preAuthorizationId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Update the pre-authorization record
        $preAuth = PreAuthorization::find($this->preAuthorizationId);
        if ($preAuth) {
            $preAuth->update([
                'status' => 'submission_failed',
                'payer_response' => ['error' => $exception->getMessage()],
            ]);
        }
    }
} 
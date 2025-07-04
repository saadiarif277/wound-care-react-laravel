<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Docuseal\DocusealSubmission;

/**
 * DocusealWebhookController
 * 
 * Handles webhook notifications from Docuseal
 */
class DocusealWebhookController extends Controller
{
    /**
     * Handle incoming Docuseal webhook
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            
            Log::info('Docuseal webhook received', [
                'event_type' => $payload['event_type'] ?? 'unknown',
                'submission_id' => $payload['data']['id'] ?? null,
                'template_id' => $payload['data']['template']['id'] ?? null,
            ]);

            // Handle different webhook events
            $eventType = $payload['event_type'] ?? null;
            
            switch ($eventType) {
                case 'form.completed':
                    $this->handleFormCompleted($payload);
                    break;
                    
                case 'form.submitted':
                    $this->handleFormSubmitted($payload);
                    break;
                    
                case 'form.signed':
                    $this->handleFormSigned($payload);
                    break;
                    
                default:
                    Log::info('Unhandled Docuseal webhook event', [
                        'event_type' => $eventType,
                        'payload' => $payload,
                    ]);
            }

            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::error('Failed to process Docuseal webhook', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Failed to process webhook'], 500);
        }
    }

    /**
     * Handle form completed event
     */
    private function handleFormCompleted(array $payload): void
    {
        $submissionData = $payload['data'] ?? [];
        $submissionId = $submissionData['id'] ?? null;

        if (!$submissionId) {
            Log::warning('Docuseal webhook missing submission ID');
            return;
        }

        // Update or create submission record
        DocusealSubmission::updateOrCreate(
            ['submission_id' => $submissionId],
            [
                'template_id' => $submissionData['template']['id'] ?? null,
                'status' => 'completed',
                'completed_at' => now(),
                'submission_data' => $submissionData,
            ]
        );

        Log::info('Docuseal form completed', [
            'submission_id' => $submissionId,
        ]);
    }

    /**
     * Handle form submitted event
     */
    private function handleFormSubmitted(array $payload): void
    {
        $submissionData = $payload['data'] ?? [];
        $submissionId = $submissionData['id'] ?? null;

        if (!$submissionId) {
            Log::warning('Docuseal webhook missing submission ID');
            return;
        }

        // Update submission record
        DocusealSubmission::updateOrCreate(
            ['submission_id' => $submissionId],
            [
                'template_id' => $submissionData['template']['id'] ?? null,
                'status' => 'submitted',
                'submitted_at' => now(),
                'submission_data' => $submissionData,
            ]
        );

        Log::info('Docuseal form submitted', [
            'submission_id' => $submissionId,
        ]);
    }

    /**
     * Handle form signed event
     */
    private function handleFormSigned(array $payload): void
    {
        $submissionData = $payload['data'] ?? [];
        $submissionId = $submissionData['id'] ?? null;

        if (!$submissionId) {
            Log::warning('Docuseal webhook missing submission ID');
            return;
        }

        // Update submission record
        DocusealSubmission::updateOrCreate(
            ['submission_id' => $submissionId],
            [
                'template_id' => $submissionData['template']['id'] ?? null,
                'status' => 'signed',
                'signed_at' => now(),
                'submission_data' => $submissionData,
            ]
        );

        Log::info('Docuseal form signed', [
            'submission_id' => $submissionId,
        ]);
    }
} 
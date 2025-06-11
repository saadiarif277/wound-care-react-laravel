<?php

namespace App\Http\Controllers;

use App\Services\IvrDocusealService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocuSealWebhookController extends Controller
{
    private IvrDocusealService $ivrService;

    public function __construct(IvrDocusealService $ivrService)
    {
        $this->ivrService = $ivrService;
    }

    /**
     * Handle DocuSeal webhook events
     */
    public function handle(Request $request)
    {
        // Log the webhook for debugging
        Log::info('DocuSeal webhook received', [
            'event' => $request->input('event'),
            'data' => $request->all()
        ]);

        // Verify webhook signature if provided
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Invalid DocuSeal webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        try {
            switch ($event) {
                case 'submission.completed':
                    $this->handleSubmissionCompleted($data);
                    break;
                    
                case 'submission.created':
                    $this->handleSubmissionCreated($data);
                    break;
                    
                case 'submission.updated':
                    $this->handleSubmissionUpdated($data);
                    break;
                    
                default:
                    Log::info('Unhandled DocuSeal webhook event', ['event' => $event]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('DocuSeal webhook processing failed', [
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle submission completed event
     */
    private function handleSubmissionCompleted(array $data)
    {
        $submissionId = $data['id'] ?? null;
        
        if (!$submissionId) {
            Log::warning('Submission completed webhook missing submission ID');
            return;
        }

        // Process IVR signature
        $this->ivrService->processIvrSignature($submissionId);
    }

    /**
     * Handle submission created event
     */
    private function handleSubmissionCreated(array $data)
    {
        Log::info('DocuSeal submission created', [
            'submission_id' => $data['id'] ?? null,
            'template_id' => $data['template_id'] ?? null
        ]);
    }

    /**
     * Handle submission updated event
     */
    private function handleSubmissionUpdated(array $data)
    {
        Log::info('DocuSeal submission updated', [
            'submission_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null
        ]);
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookSecret = config('services.docuseal.webhook_secret');
        
        // If no secret is configured, skip verification (not recommended for production)
        if (empty($webhookSecret)) {
            Log::warning('DocuSeal webhook secret not configured');
            return true; // Allow for development, but log warning
        }

        $signature = $request->header('X-DocuSeal-Signature');
        if (!$signature) {
            return false;
        }

        // DocuSeal uses HMAC-SHA256 for webhook signatures
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
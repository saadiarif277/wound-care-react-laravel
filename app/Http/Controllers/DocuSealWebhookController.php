<?php

namespace App\Http\Controllers;

use App\Services\IvrDocusealService;
use App\Models\PatientIVRStatus;
use App\Models\Order\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            'event_type' => $request->input('event_type'),
            'data' => $request->all()
        ]);

        // Verify webhook signature if provided
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Invalid DocuSeal webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $request->input('event_type');
        $data = $request->input('data');

        try {
            switch ($eventType) {
                case 'form.completed':
                    $this->handleFormCompleted($data);
                    break;

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
                    Log::info('Unhandled DocuSeal webhook event', ['event' => $eventType]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('DocuSeal webhook processing failed', [
                'event' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle form.completed event (new format)
     */
    private function handleFormCompleted(array $data)
    {
        $submissionId = $data['submission']['id'] ?? $data['submission_id'] ?? null;
        $submitterEmail = $data['email'] ?? null;

        if (!$submissionId) {
            Log::warning('Form completed webhook missing submission ID');
            return;
        }

        Log::info('Processing form.completed event', [
            'submission_id' => $submissionId,
            'submitter_email' => $submitterEmail,
            'template_name' => $data['template']['name'] ?? 'Unknown',
        ]);

        // Extract patient information from the form values
        $patientInfo = $this->extractPatientInfo($data['values'] ?? []);

        // Find the episode based on patient information
        $episode = $this->findEpisodeByPatientInfo($patientInfo, $data);

        if ($episode) {
            // Store the completed IVR document in the episode
            $this->storeIVRDocument($episode, $data);

            // Update episode status if it's waiting for IVR
            if ($episode->status === 'ready_for_review' && $episode->ivr_status !== 'provider_completed') {
                $episode->update([
                    'ivr_status' => 'provider_completed',
                    'docuseal_submission_id' => $submissionId,
                    'docuseal_status' => 'completed',
                    'docuseal_signed_at' => now(),
                ]);

                Log::info('Episode IVR status updated', [
                    'episode_id' => $episode->id,
                    'submission_id' => $submissionId,
                ]);
            }
        } else {
            Log::warning('Could not find episode for completed IVR', [
                'submission_id' => $submissionId,
                'patient_info' => $patientInfo,
            ]);
        }
    }

    /**
     * Extract patient information from form values
     */
    private function extractPatientInfo(array $values): array
    {
        $patientInfo = [
            'patient_name' => null,
            'patient_dob' => null,
            'patient_id' => null,
            'provider_name' => null,
            'facility_name' => null,
        ];

        foreach ($values as $field) {
            $fieldName = $field['field'] ?? '';
            $value = $field['value'] ?? '';

            switch ($fieldName) {
                case 'Patient Name':
                    $patientInfo['patient_name'] = $value;
                    break;
                case 'Patient DOB':
                    $patientInfo['patient_dob'] = $value;
                    break;
                case 'Physician Name':
                    $patientInfo['provider_name'] = $value;
                    break;
                case 'Facility Name':
                    $patientInfo['facility_name'] = $value;
                    break;
            }
        }

        return $patientInfo;
    }

    /**
     * Find episode based on patient information
     */
    private function findEpisodeByPatientInfo(array $patientInfo, array $webhookData): ?PatientIVRStatus
    {
        // Try to find by external_id if present in submitter data
        if (!empty($webhookData['external_id'])) {
            $episode = PatientIVRStatus::where('id', $webhookData['external_id'])->first();
            if ($episode) {
                Log::info('Found episode by external_id', [
                    'episode_id' => $episode->id,
                    'external_id' => $webhookData['external_id'],
                ]);
                return $episode;
            }
        }

        // Try to find by patient name pattern (e.g., JO**#7842)
        if (!empty($patientInfo['patient_name'])) {
            // Extract the pattern if it matches our format
            if (preg_match('/^[A-Z]{2}\*\*#\d+$/', $patientInfo['patient_name'])) {
                $episode = PatientIVRStatus::where('patient_display_id', $patientInfo['patient_name'])->first();
                if ($episode) {
                    return $episode;
                }
            }
        }

        // Try to find recent episodes that are waiting for IVR
        $recentEpisode = PatientIVRStatus::where('status', 'ready_for_review')
            ->where('ivr_status', '!=', 'provider_completed')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->first();

        if ($recentEpisode) {
            Log::info('Found recent episode waiting for IVR', [
                'episode_id' => $recentEpisode->id,
                'patient_info' => $patientInfo,
            ]);
            return $recentEpisode;
        }

        return null;
    }

    /**
     * Store IVR document in episode metadata
     */
    private function storeIVRDocument(PatientIVRStatus $episode, array $webhookData): void
    {
        $documents = $webhookData['documents'] ?? [];
        $metadata = $episode->metadata ?? [];

        // Initialize documents array if not exists
        if (!isset($metadata['documents'])) {
            $metadata['documents'] = [];
        }

        // Add IVR documents
        foreach ($documents as $doc) {
            $documentRecord = [
                'id' => Str::uuid()->toString(),
                'type' => 'ivr',
                'name' => $doc['name'] ?? 'IVR Document',
                'url' => $doc['url'] ?? '',
                'docuseal_submission_id' => $webhookData['submission']['id'] ?? null,
                'uploaded_at' => now()->toISOString(),
                'uploaded_by' => 'DocuSeal Webhook',
                'file_size' => null,
                'mime_type' => 'application/pdf',
            ];

            $metadata['documents'][] = $documentRecord;
        }

        // Add audit log URL if available
        if (!empty($webhookData['audit_log_url'])) {
            $metadata['docuseal_audit_log_url'] = $webhookData['audit_log_url'];
        }

        // Store form values for reference
        if (!empty($webhookData['values'])) {
            $metadata['ivr_form_values'] = $webhookData['values'];
        }

        $episode->update(['metadata' => $metadata]);

        Log::info('IVR document stored in episode', [
            'episode_id' => $episode->id,
            'document_count' => count($documents),
        ]);
    }

    /**
     * Handle submission completed event (legacy format)
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

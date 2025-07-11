<?php

namespace App\Http\Controllers;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\ProductRequest;
use App\Models\Order\Order;
use App\Models\Docuseal\DocusealSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocusealWebhookController extends Controller
{
    public function __construct()
    {
        // No service dependencies needed - webhook processing is self-contained
    }

    /**
     * Handle Docuseal webhook events
     */
    public function handle(Request $request)
    {
        // Log the webhook for debugging
        Log::info('Docuseal webhook received', [
            'event_type' => $request->input('event_type'),
            'data' => $request->all()
        ]);

        // Verify webhook signature if provided
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Invalid Docuseal webhook signature');
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
                    Log::info('Unhandled Docuseal webhook event', ['event' => $eventType]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Docuseal webhook processing failed', [
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
        $documents = $data['documents'] ?? [];

        if (!$submissionId) {
            Log::warning('Form completed webhook missing submission ID');
            return;
        }

        Log::info('Processing form.completed event', [
            'submission_id' => $submissionId,
            'submitter_email' => $submitterEmail,
            'template_name' => $data['template']['name'] ?? 'Unknown',
            'document_count' => count($documents),
        ]);

        // Find existing DocuSeal submission record
        $docusealSubmission = DocusealSubmission::where('docuseal_submission_id', $submissionId)->first();
        
        if (!$docusealSubmission) {
            // Try to find episode by extracted patient info for backwards compatibility
            $patientInfo = $this->extractPatientInfo($data['values'] ?? []);
            $episode = $this->findEpisodeByPatientInfo($patientInfo, $data);
            
            if (!$episode) {
                Log::warning('Could not find episode or DocuSeal submission record', [
                    'submission_id' => $submissionId,
                    'patient_info' => $patientInfo,
                ]);
                return;
            }

            // Create submission record if not found (backwards compatibility)
            $templateName = $data['template']['name'] ?? '';
            $isOrderForm = $this->isOrderFormTemplate($templateName, $data);
            
            $docusealSubmission = DocusealSubmission::create([
                'order_id' => $episode->orders->first()?->id,
                'docuseal_submission_id' => $submissionId,
                'docuseal_template_id' => $data['template']['id'] ?? null,
                'document_type' => $isOrderForm ? 'OrderForm' : 'IVR',
                'status' => 'pending',
                'folder_id' => $data['template']['folder_name'] ?? 'Default',
                'metadata' => [
                    'template_name' => $templateName,
                    'submitter_email' => $submitterEmail,
                ]
            ]);
        }

        // Update submission with completion data
        $documentUrl = !empty($documents) ? $documents[0]['url'] : null;
        
        $docusealSubmission->update([
            'status' => 'completed',
            'document_url' => $documentUrl,
            'completed_at' => now(),
            'metadata' => array_merge($docusealSubmission->metadata ?? [], [
                'completed_at' => now()->toIso8601String(),
                'documents' => $documents,
                'audit_log_url' => $data['audit_log_url'] ?? null,
                'form_values' => $data['values'] ?? [],
                'submitter_info' => [
                    'email' => $submitterEmail,
                    'ip' => $data['ip'] ?? null,
                    'user_agent' => $data['ua'] ?? null,
                ]
            ])
        ]);

        // Update episode status based on document type
        $episode = $docusealSubmission->order?->episode ?? PatientManufacturerIVREpisode::find($docusealSubmission->order?->episode_id);
        
        if ($episode && $docusealSubmission->document_type === 'IVR') {
            $this->updateEpisodeIVRStatus($episode, $docusealSubmission);
        } elseif ($episode && $docusealSubmission->document_type === 'OrderForm') {
            $this->updateEpisodeOrderFormStatus($episode, $docusealSubmission);
        }

        Log::info('Document completion processed successfully', [
            'submission_id' => $submissionId,
            'document_type' => $docusealSubmission->document_type,
            'episode_id' => $episode?->id,
            'document_url' => $documentUrl,
        ]);
    }

    /**
     * Update episode IVR status when IVR document is completed
     */
    private function updateEpisodeIVRStatus(PatientManufacturerIVREpisode $episode, DocusealSubmission $submission): void
    {
        if ($episode->status === 'ready_for_review' && $episode->ivr_status !== 'provider_completed') {
            $episode->update([
                'ivr_status' => 'provider_completed',
                'docuseal_submission_id' => $submission->docuseal_submission_id,
                'docuseal_status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('Episode IVR status updated', [
                'episode_id' => $episode->id,
                'submission_id' => $submission->docuseal_submission_id,
            ]);
        }
    }

    /**
     * Update episode order form status when order form is completed  
     */
    private function updateEpisodeOrderFormStatus(PatientManufacturerIVREpisode $episode, DocusealSubmission $submission): void
    {
        $episode->update([
            'order_form_status' => 'completed',
            'order_form_submission_id' => $submission->docuseal_submission_id,
            'order_form_completed_at' => now(),
        ]);

        Log::info('Episode order form status updated', [
            'episode_id' => $episode->id,
            'submission_id' => $submission->docuseal_submission_id,
        ]);
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
    private function findEpisodeByPatientInfo(array $patientInfo, array $webhookData): ?PatientManufacturerIVREpisode
    {
        // Try to find by external_id if present in submitter data
        if (!empty($webhookData['external_id'])) {
            $episode = PatientManufacturerIVREpisode::where('id', $webhookData['external_id'])->first();
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
                $episode = PatientManufacturerIVREpisode::where('patient_display_id', $patientInfo['patient_name'])->first();
                if ($episode) {
                    return $episode;
                }
            }
        }

        // Try to find recent episodes that are waiting for IVR
        $recentEpisode = PatientManufacturerIVREpisode::where('status', 'ready_for_review')
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
    private function storeIVRDocument(PatientManufacturerIVREpisode $episode, array $webhookData): void
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
                'uploaded_by' => 'Docuseal Webhook',
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
     * Store order form document in episode metadata
     */
    private function storeOrderFormDocument(PatientManufacturerIVREpisode $episode, array $webhookData): void
    {
        $documents = $webhookData['documents'] ?? [];
        $metadata = $episode->forms_metadata ?? [];

        // Initialize order_forms array if not exists
        if (!isset($metadata['order_forms'])) {
            $metadata['order_forms'] = [];
        }

        // Add order form documents
        foreach ($documents as $doc) {
            $documentRecord = [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'order_form',
                'name' => $doc['name'] ?? 'Order Form Document',
                'url' => $doc['url'] ?? '',
                'docuseal_submission_id' => $webhookData['submission']['id'] ?? null,
                'uploaded_at' => now()->toISOString(),
                'uploaded_by' => 'Docuseal Webhook',
                'file_size' => null,
                'mime_type' => 'application/pdf',
            ];

            $metadata['order_forms'][] = $documentRecord;
        }

        // Add audit log URL if available
        if (!empty($webhookData['audit_log_url'])) {
            $metadata['order_form_audit_log_url'] = $webhookData['audit_log_url'];
        }

        // Store form values for reference
        if (!empty($webhookData['values'])) {
            $metadata['order_form_values'] = $webhookData['values'];
        }

        $episode->update(['forms_metadata' => $metadata]);

        Log::info('Order form document stored in episode', [
            'episode_id' => $episode->id,
            'document_count' => count($documents),
        ]);
    }

    /**
     * Determine if the completed template is an order form
     */
    private function isOrderFormTemplate(string $templateName, array $webhookData): bool
    {
        // Check template name patterns that indicate order forms
        $orderFormPatterns = [
            'order form',
            'order_form',
            'orderform',
            'purchase order',
            'product order',
        ];

        $lowerTemplateName = strtolower($templateName);

        foreach ($orderFormPatterns as $pattern) {
            if (strpos($lowerTemplateName, $pattern) !== false) {
                return true;
            }
        }

        // Check if template has specific order form fields
        $values = $webhookData['values'] ?? [];
        $orderFormFields = ['product_quantity', 'shipping_address', 'order_total', 'delivery_date'];

        foreach ($values as $field) {
            $fieldName = strtolower($field['field'] ?? '');
            foreach ($orderFormFields as $orderField) {
                if (strpos($fieldName, $orderField) !== false) {
                    return true;
                }
            }
        }

        return false;
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

        // Legacy submission completed event - now handled by form.completed event
        Log::info('Legacy submission completed event received', [
            'submission_id' => $submissionId,
            'note' => 'Processing moved to form.completed event handler'
        ]);
    }

    /**
     * Handle submission created event
     */
    private function handleSubmissionCreated(array $data)
    {
        Log::info('Docuseal submission created', [
            'submission_id' => $data['id'] ?? null,
            'template_id' => $data['template_id'] ?? null
        ]);
    }

    /**
     * Handle submission updated event
     */
    private function handleSubmissionUpdated(array $data)
    {
        Log::info('Docuseal submission updated', [
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
            Log::warning('Docuseal webhook secret not configured');
            return true; // Allow for development, but log warning
        }

        $signature = $request->header('X-Docuseal-Signature');
        if (!$signature) {
            return false;
        }

        // Docuseal uses HMAC-SHA256 for webhook signatures
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }
}

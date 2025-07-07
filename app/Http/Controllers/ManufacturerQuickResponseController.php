<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ManufacturerSubmission;
use App\Services\SmartEmailSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Response;

class ManufacturerQuickResponseController extends Controller
{
    private SmartEmailSender $emailSender;

    public function __construct(SmartEmailSender $emailSender)
    {
        $this->emailSender = $emailSender;
    }

    /**
     * Handle quick approve/deny clicks from email
     */
    public function quickResponse(Request $request, string $token, string $action)
    {
        try {
            // Validate action
            if (!in_array($action, ['approve', 'deny'])) {
                return $this->showError('Invalid action specified.');
            }

            // Find the submission
            $submission = ManufacturerSubmission::findValidToken($token);
            
            if (!$submission) {
                return $this->showError('This link has expired or has already been used.');
            }

            // Update submission status
            $response = $action === 'approve' ? 'approved' : 'denied';
            $submission->update([
                'status' => $response,
                'responded_at' => now(),
                'response_ip' => $request->ip(),
                'response_user_agent' => $request->userAgent(),
            ]);

            // Log the response
            Log::info('Manufacturer quick response', [
                'submission_id' => $submission->id,
                'order_id' => $submission->order_id,
                'manufacturer_id' => $submission->manufacturer_id,
                'response' => $response,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send admin notification
            $this->sendAdminNotification($submission, $response);

            // Show confirmation page
            $orderDetails = $submission->order_details ?? [];
            $orderNumber = $orderDetails['order_number'] ?? $submission->order_id;
            
            return view('manufacturer.response-confirmed', [
                'action' => $action,
                'response' => $response,
                'orderNumber' => $orderNumber,
                'manufacturerName' => $submission->manufacturer_name,
                'submissionId' => $submission->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in manufacturer quick response', [
                'token' => $token,
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->showError('An error occurred while processing your response. Please try again.');
        }
    }

    /**
     * View document in browser (optional)
     */
    public function viewDocument(string $token)
    {
        try {
            $submission = ManufacturerSubmission::findByToken($token);
            
            if (!$submission) {
                return $this->showError('This link has expired or is invalid.');
            }

            $urls = $submission->generateUrls();
            
            $orderDetails = $submission->order_details ?? [];
            $orderNumber = $orderDetails['order_number'] ?? $submission->order_id;
            
            return view('manufacturer.view-document', [
                'submission' => $submission,
                'orderNumber' => $orderNumber,
                'manufacturerName' => $submission->manufacturer_name,
                'pdfUrl' => $submission->pdf_url,
                'pdfFilename' => $submission->pdf_filename,
                'orderDetails' => $submission->order_details,
                'approveUrl' => $urls['approve'],
                'denyUrl' => $urls['deny'],
                'isPending' => $submission->isPending(),
                'hasResponded' => $submission->hasResponded(),
                'response' => $submission->status,
                'responseTime' => $submission->response_time,
                'expiresAt' => $submission->expires_at,
            ]);

        } catch (\Exception $e) {
            Log::error('Error viewing manufacturer document', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return $this->showError('An error occurred while loading the document.');
        }
    }

    /**
     * Handle form-based response with notes
     */
    public function submitResponse(Request $request, string $token)
    {
        try {
            $request->validate([
                'response' => 'required|in:approve,deny',
                'notes' => 'nullable|string|max:1000',
            ]);

            $submission = ManufacturerSubmission::findValidToken($token);
            
            if (!$submission) {
                return response()->json([
                    'error' => 'This link has expired or has already been used.'
                ], 404);
            }

            $response = $request->input('response') === 'approve' ? 'approved' : 'denied';
            $notes = $request->input('notes');

            $submission->update([
                'status' => $response,
                'responded_at' => now(),
                'response_ip' => $request->ip(),
                'response_user_agent' => $request->userAgent(),
                'response_notes' => $notes,
            ]);

            Log::info('Manufacturer form response', [
                'submission_id' => $submission->id,
                'order_id' => $submission->order_id,
                'response' => $response,
                'has_notes' => !empty($notes),
            ]);

            // Send admin notification
            $this->sendAdminNotification($submission, $response);

            $orderDetails = $submission->order_details ?? [];
            $orderNumber = $orderDetails['order_number'] ?? $submission->order_id;
            
            return response()->json([
                'success' => true,
                'message' => 'Your response has been recorded successfully.',
                'response' => $response,
                'order_number' => $orderNumber,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in manufacturer form response', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'An error occurred while processing your response.'
            ], 500);
        }
    }

    /**
     * Get submission status (for polling)
     */
    public function getStatus(string $token)
    {
        try {
            $submission = ManufacturerSubmission::findByToken($token);
            
            if (!$submission) {
                return response()->json(['error' => 'Submission not found'], 404);
            }

            return response()->json([
                'status' => $submission->status,
                'responded_at' => $submission->responded_at,
                'response_time' => $submission->response_time,
                'expires_at' => $submission->expires_at,
                'is_expired' => $submission->isExpired(),
                'is_pending' => $submission->isPending(),
                'has_responded' => $submission->hasResponded(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting submission status', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Server error'], 500);
        }
    }

    /**
     * Send admin notification about manufacturer response
     */
    private function sendAdminNotification(ManufacturerSubmission $submission, string $response)
    {
        try {
            // Skip if already sent
            if ($submission->notification_sent) {
                return;
            }

            $orderDetails = $submission->order_details ?? [];
            $statusEmoji = $response === 'approved' ? '✅' : '❌';
            $statusText = strtoupper($response);

            $context = [
                'manufacturer_id' => $submission->manufacturer_id,
                'document_type' => 'ivr_response',
                'order_id' => $submission->order_id,
            ];

            $adminEmails = config('app.admin_emails', ['admin@mscwoundcare.com']);
            $orderNumber = $orderDetails['order_number'] ?? $submission->order_id;
            $patientId = $orderDetails['patient_id'] ?? 'N/A';
            $productName = $orderDetails['product_name'] ?? 'N/A';
            $providerName = $orderDetails['provider_name'] ?? null;
            $facilityName = $orderDetails['facility_name'] ?? null;
            
            $htmlContent = view('emails.manufacturer.admin-notification', [
                'response' => $response,
                'statusEmoji' => $statusEmoji,
                'statusText' => $statusText,
                'orderNumber' => $orderNumber,
                'patientId' => $patientId,
                'manufacturerName' => $submission->manufacturer_name,
                'productName' => $productName,
                'providerName' => $providerName,
                'facilityName' => $facilityName,
                'responseTime' => $submission->responded_at->format('F j, Y g:i A'),
                'responseNotes' => $submission->response_notes,
                'responseTimeFromSent' => $submission->response_time,
                'orderDetailsUrl' => url("/admin/orders/{$submission->order_id}"),
            ])->render();

            $result = $this->emailSender->send(
                $adminEmails,
                "{$statusEmoji} Manufacturer {$statusText}: Order #{$orderNumber}",
                $htmlContent,
                $context
            );

            if ($result['success']) {
                $submission->update(['notification_sent' => true]);
                Log::info('Admin notification sent for manufacturer response', [
                    'submission_id' => $submission->id,
                    'response' => $response,
                ]);
            } else {
                Log::error('Failed to send admin notification', [
                    'submission_id' => $submission->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error sending admin notification', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show error page
     */
    private function showError(string $message)
    {
        return view('manufacturer.response-error', [
            'message' => $message,
            'supportEmail' => config('app.support_email', 'support@mscwoundcare.com'),
        ]);
    }

    /**
     * Get manufacturer submission statistics
     */
    public function getStats(Request $request)
    {
        try {
            $manufacturerId = $request->input('manufacturer_id');
            $days = $request->input('days', 30);

            if (!$manufacturerId) {
                return response()->json(['error' => 'Manufacturer ID required'], 400);
            }

            $stats = ManufacturerSubmission::getStatsForManufacturer($manufacturerId);
            $activity = ManufacturerSubmission::getRecentActivity($days);

            return response()->json([
                'stats' => $stats,
                'activity' => $activity,
                'manufacturer_id' => $manufacturerId,
                'period_days' => $days,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting manufacturer stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Server error'], 500);
        }
    }

    /**
     * Expire old submissions (can be called by cron)
     */
    public function expireOldSubmissions()
    {
        try {
            $expired = ManufacturerSubmission::expireOldSubmissions();
            
            Log::info('Expired old manufacturer submissions', [
                'count' => $expired,
            ]);

            return response()->json([
                'success' => true,
                'expired_count' => $expired,
            ]);

        } catch (\Exception $e) {
            Log::error('Error expiring old submissions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Server error'], 500);
        }
    }
}

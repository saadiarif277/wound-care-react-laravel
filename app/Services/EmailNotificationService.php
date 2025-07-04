<?php

namespace App\Services;

use App\Models\Order\OrderEmailNotification;
use App\Models\Order\ProductRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class EmailNotificationService
{
    /**
     * Send status change notification
     */
    public function sendStatusChangeNotification(
        ProductRequest $order,
        string $previousStatus,
        string $newStatus,
        string $changedBy,
        ?string $notes = null
    ): bool {
        try {
            // Get notification recipients
            $recipients = $this->getNotificationRecipients($order);

            if (empty($recipients)) {
                Log::info('No recipients found for order status change notification', [
                    'order_id' => $order->id,
                    'new_status' => $newStatus
                ]);
                return false;
            }

            $successCount = 0;
            $totalRecipients = count($recipients);

            foreach ($recipients as $recipient) {
                $notification = $this->createNotificationRecord(
                    $order,
                    'status_change',
                    $recipient['email'],
                    $recipient['name'] ?? null,
                    $previousStatus,
                    $newStatus,
                    $changedBy,
                    $notes
                );

                if ($this->sendEmail($notification)) {
                    $successCount++;
                }
            }

            // Update order success notification tracking
            if ($successCount > 0) {
                $order->update([
                    'last_success_notification_at' => now(),
                    'success_notification_count' => $order->success_notification_count + $successCount,
                ]);
            }

            Log::info('Status change notification sent', [
                'order_id' => $order->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'recipients' => $totalRecipients,
                'successful_sends' => $successCount,
            ]);

            return $successCount > 0;

        } catch (Exception $e) {
            Log::error('Failed to send status change notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Send IVR sent notification
     */
    public function sendIvrSentNotification(ProductRequest $order): bool
    {
        try {
            $recipients = $this->getNotificationRecipients($order);

            if (empty($recipients)) {
                return false;
            }

            $successCount = 0;

            foreach ($recipients as $recipient) {
                $notification = $this->createNotificationRecord(
                    $order,
                    'ivr_sent',
                    $recipient['email'],
                    $recipient['name'] ?? null
                );

                if ($this->sendEmail($notification)) {
                    $successCount++;
                }
            }

            return $successCount > 0;

        } catch (Exception $e) {
            Log::error('Failed to send IVR sent notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send IVR completed notification
     */
    public function sendIvrCompletedNotification(ProductRequest $order): bool
    {
        try {
            $recipients = $this->getNotificationRecipients($order);

            if (empty($recipients)) {
                return false;
            }

            $successCount = 0;

            foreach ($recipients as $recipient) {
                $notification = $this->createNotificationRecord(
                    $order,
                    'ivr_completed',
                    $recipient['email'],
                    $recipient['name'] ?? null
                );

                if ($this->sendEmail($notification)) {
                    $successCount++;
                }
            }

            return $successCount > 0;

        } catch (Exception $e) {
            Log::error('Failed to send IVR completed notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get notification recipients for an order
     */
    private function getNotificationRecipients(ProductRequest $order): array
    {
        $recipients = [];

        // Check if email notifications are enabled
        if (!$order->email_notifications_enabled) {
            return $recipients;
        }

        // Get recipients from order settings
        if ($order->notification_recipients) {
            $recipients = array_merge($recipients, $order->notification_recipients);
        }

        // Add provider email
        if ($order->provider && $order->provider->email) {
            $recipients[] = [
                'email' => $order->provider->email,
                'name' => $order->provider->name ?? 'Provider',
            ];
        }

        // Add facility contact email if available
        if ($order->facility && $order->facility->contact_email) {
            $recipients[] = [
                'email' => $order->facility->contact_email,
                'name' => $order->facility->name ?? 'Facility',
            ];
        }

        // Remove duplicates
        $uniqueRecipients = [];
        $seenEmails = [];

        foreach ($recipients as $recipient) {
            $email = strtolower(trim($recipient['email']));
            if (!empty($email) && !in_array($email, $seenEmails)) {
                $uniqueRecipients[] = $recipient;
                $seenEmails[] = $email;
            }
        }

        return $uniqueRecipients;
    }

    /**
     * Create notification record
     */
    private function createNotificationRecord(
        ProductRequest $order,
        string $type,
        string $email,
        ?string $name = null,
        ?string $previousStatus = null,
        ?string $newStatus = null,
        ?string $changedBy = null,
        ?string $notes = null
    ): OrderEmailNotification {
        $subject = $this->getEmailSubject($type, $order, $newStatus);
        $content = $this->getEmailContent($type, $order, $previousStatus, $newStatus, $changedBy, $notes);

        return OrderEmailNotification::create([
            'order_id' => $order->id,
            'notification_type' => $type,
            'recipient_email' => $email,
            'recipient_name' => $name,
            'subject' => $subject,
            'content' => $content,
            'status' => 'pending',
            'metadata' => [
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'changed_by' => $changedBy,
                'notes' => $notes,
            ],
        ]);
    }

    /**
     * Get email subject based on notification type
     */
    private function getEmailSubject(string $type, ProductRequest $order, ?string $newStatus = null): string
    {
        $orderNumber = $order->request_number ?? $order->id;
        $patientName = $order->patient_display_id ?? 'Patient';

        switch ($type) {
            case 'status_change':
                $statusLabel = $this->getStatusLabel($newStatus);
                return "Order Status Updated - {$statusLabel} - Order #{$orderNumber}";

            case 'ivr_sent':
                return "IVR Sent - Order #{$orderNumber} for {$patientName}";

            case 'ivr_completed':
                return "IVR Completed - Order #{$orderNumber} for {$patientName}";

            case 'order_approved':
                return "Order Approved - Order #{$orderNumber} for {$patientName}";

            case 'order_denied':
                return "Order Denied - Order #{$orderNumber} for {$patientName}";

            case 'order_shipped':
                return "Order Shipped - Order #{$orderNumber} for {$patientName}";

            case 'order_delivered':
                return "Order Delivered - Order #{$orderNumber} for {$patientName}";

            default:
                return "Order Update - Order #{$orderNumber} for {$patientName}";
        }
    }

    /**
     * Get email content based on notification type
     */
    private function getEmailContent(
        string $type,
        ProductRequest $order,
        ?string $previousStatus = null,
        ?string $newStatus = null,
        ?string $changedBy = null,
        ?string $notes = null
    ): string {
        $orderNumber = $order->request_number ?? $order->id;
        $patientName = $order->patient_display_id ?? 'Patient';
        $orderUrl = route('admin.orders.show', $order->id);

        switch ($type) {
            case 'status_change':
                $previousLabel = $this->getStatusLabel($previousStatus);
                $newLabel = $this->getStatusLabel($newStatus);
                $changedByText = $changedBy ? " by {$changedBy}" : '';

                $content = "The status of Order #{$orderNumber} for {$patientName} has been updated{$changedByText}.\n\n";
                $content .= "Previous Status: {$previousLabel}\n";
                $content .= "New Status: {$newLabel}\n\n";

                if ($notes) {
                    $content .= "Notes: {$notes}\n\n";
                }

                $content .= "View Order: {$orderUrl}";
                return $content;

            case 'ivr_sent':
                return "An IVR (Insurance Verification Request) has been sent for Order #{$orderNumber} for {$patientName}.\n\nView Order: {$orderUrl}";

            case 'ivr_completed':
                return "The IVR (Insurance Verification Request) has been completed for Order #{$orderNumber} for {$patientName}.\n\nView Order: {$orderUrl}";

            case 'order_approved':
                return "Order #{$orderNumber} for {$patientName} has been approved and is ready for processing.\n\nView Order: {$orderUrl}";

            case 'order_denied':
                return "Order #{$orderNumber} for {$patientName} has been denied.\n\nView Order: {$orderUrl}";

            case 'order_shipped':
                return "Order #{$orderNumber} for {$patientName} has been shipped.\n\nView Order: {$orderUrl}";

            case 'order_delivered':
                return "Order #{$orderNumber} for {$patientName} has been delivered.\n\nView Order: {$orderUrl}";

            default:
                return "Order #{$orderNumber} for {$patientName} has been updated.\n\nView Order: {$orderUrl}";
        }
    }

    /**
     * Get status label
     */
    private function getStatusLabel(?string $status): string
    {
        $labels = [
            'pending' => 'Pending',
            'pending_ivr' => 'Pending IVR',
            'ivr_sent' => 'IVR Sent',
            'ivr_confirmed' => 'IVR Confirmed',
            'approved' => 'Approved',
            'sent_back' => 'Sent Back',
            'denied' => 'Denied',
            'submitted_to_manufacturer' => 'Submitted to Manufacturer',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        return $labels[$status] ?? $status ?? 'Unknown';
    }

    /**
     * Send email notification
     */
    private function sendEmail(OrderEmailNotification $notification): bool
    {
        try {
            // Send actual email using Laravel Mail facade
            Mail::raw($notification->content, function ($message) use ($notification) {
                $message->to($notification->recipient_email)
                        ->subject($notification->subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $notification->markAsSent('message-id-' . time());

            Log::info('Email notification sent successfully', [
                'notification_id' => $notification->id,
                'recipient' => $notification->recipient_email,
                'type' => $notification->notification_type,
                'subject' => $notification->subject,
            ]);

            return true;

        } catch (Exception $e) {
            $notification->markAsFailed($e->getMessage());

            Log::error('Failed to send email notification', [
                'notification_id' => $notification->id,
                'recipient' => $notification->recipient_email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get notification statistics for an order
     */
    public function getNotificationStats(ProductRequest $order): array
    {
        $notifications = OrderEmailNotification::forOrder($order->id)->get();

        return [
            'total' => $notifications->count(),
            'pending' => $notifications->where('status', 'pending')->count(),
            'sent' => $notifications->where('status', 'sent')->count(),
            'delivered' => $notifications->where('status', 'delivered')->count(),
            'failed' => $notifications->where('status', 'failed')->count(),
            'recent' => $notifications->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}

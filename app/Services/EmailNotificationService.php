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
     * Implements notifications #2 and #3 based on status type
     */
    public function sendStatusChangeNotification(
        ProductRequest $order,
        string $previousStatus,
        string $newStatus,
        string $changedBy,
        ?string $notes = null,
        ?array $notificationDocuments = null
    ): bool {
        try {
            // Determine notification type based on status
            $isIvrStatus = in_array($newStatus, ['sent', 'verified', 'rejected']);
            $isOrderStatus = in_array($newStatus, ['submitted_to_manufacturer', 'confirmed_by_manufacturer', 'rejected', 'canceled']);
            
            if (!$isIvrStatus && !$isOrderStatus) {
                Log::info('Status change does not require notification', [
                    'order_id' => $order->id,
                    'status' => $newStatus
                ]);
                return false;
            }
            
            // Get the original requestor (Provider/OM who submitted the order)
            $requestor = $this->getOrderRequestor($order);
            
            if (!$requestor || !$requestor->email) {
                Log::warning('No requestor found for order status notification', [
                    'order_id' => $order->id
                ]);
                return false;
            }
            
            // Send notification to requestor
            return $this->sendStatusUpdateToProvider(
                $order,
                $requestor,
                $newStatus,
                $notes,
                $isIvrStatus ? 'ivr' : 'order'
            );

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
     * Send status update notification to provider/OM
     * Implements notifications #2 and #3
     */
    private function sendStatusUpdateToProvider(
        ProductRequest $order,
        $requestor,
        string $newStatus,
        ?string $comments,
        string $updateType
    ): bool {
        try {
            // Format status for display
            $displayStatus = $this->formatStatusForDisplay($newStatus);
            
            // Send email
            Mail::send('emails.order.status-update-provider', [
                'order' => [
                    'order_number' => $order->request_number,
                    'id' => $order->id
                ],
                'updateType' => $updateType,
                'newStatus' => $displayStatus,
                'comments' => $comments,
                'trackingUrl' => route('orders.track', ['order' => $order->id])
            ], function ($message) use ($requestor, $order, $displayStatus, $updateType) {
                $statusType = $updateType === 'ivr' ? 'IVR' : 'Order';
                $message->to($requestor->email, $requestor->name)
                    ->subject("Order Update – {$order->request_number} {$statusType} Status: {$displayStatus}");
            });
            
            // Create notification record
            $this->createNotificationRecord(
                $order,
                'status_update',
                $requestor->email,
                $requestor->name,
                '',
                $newStatus,
                'Admin',
                $comments
            );
            
            Log::info('Status update notification sent to provider/OM', [
                'order_id' => $order->id,
                'recipient' => $requestor->email,
                'status' => $newStatus,
                'type' => $updateType
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send status update to provider', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get the original requestor of the order
     */
    private function getOrderRequestor(ProductRequest $order)
    {
        // First try to get from order provider
        if ($order->provider) {
            return $order->provider;
        }
        
        // Then try to get from order created_by
        if ($order->created_by) {
            return \App\Models\User::find($order->created_by);
        }
        
        // Finally try to get from episode metadata
        if ($order->episode && $order->episode->created_by) {
            return \App\Models\User::find($order->episode->created_by);
        }
        
        return null;
    }
    
    /**
     * Format status for display in emails
     */
    private function formatStatusForDisplay(string $status): string
    {
        $statusMap = [
            'sent' => 'Sent',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'submitted_to_manufacturer' => 'Submitted to Manufacturer',
            'confirmed_by_manufacturer' => 'Confirmed by Manufacturer',
            'canceled' => 'Canceled'
        ];
        
        return $statusMap[$status] ?? ucfirst(str_replace('_', ' ', $status));
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

    /**
     * Send user invitation email 
     * Implements the user onboarding notification
     */
    public function sendUserInvitation(User $user, string $role): bool
    {
        try {
            Mail::send('emails.provider-invitation', [
                'user' => $user,
                'first_name' => $user->first_name,
                'role' => $role,
                'login_url' => route('login'),
            ], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                        ->subject("🚀 You're Invited to Join the MSC Wound Care Platform 🚀");
            });

            Log::info('User invitation sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $role
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send user invitation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send order request submitted notification to admin
     * Updates existing notification to match new requirements
     */
    public function sendOrderSubmittedToAdmin(ProductRequest $order, ?string $comments = null): bool
    {
        try {
            $admins = User::where('user_type', 'admin')->get();
            
            if ($admins->isEmpty()) {
                Log::warning('No admin users found to notify of order submission', [
                    'order_id' => $order->id
                ]);
                return false;
            }

            $successCount = 0;

            foreach ($admins as $admin) {
                Mail::send('emails.order.new-order-admin', [
                    'order' => $order,
                    'order_id' => $order->id,
                    'provider_name' => $order->provider->name ?? 'Unknown Provider',
                    'date' => $order->created_at->format('F j, Y'),
                    'comment' => $comments,
                    'order_link' => route('admin.orders.show', $order->id),
                ], function ($message) use ($admin, $order) {
                    $message->to($admin->email, $admin->name)
                            ->subject("📝 MSC: New Order Request Submitted by {$order->provider->name}");
                });

                $successCount++;
            }

            Log::info('Order submission notification sent to admins', [
                'order_id' => $order->id,
                'admin_count' => $successCount
            ]);

            return $successCount > 0;

        } catch (Exception $e) {
            Log::error('Failed to send order submission notification to admin', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send IVR verified notification to provider
     */
    public function sendIvrVerifiedToProvider(ProductRequest $order, ?string $comments = null): bool
    {
        try {
            $provider = $this->getOrderRequestor($order);
            
            if (!$provider || !$provider->email) {
                Log::warning('No provider found for IVR verified notification', [
                    'order_id' => $order->id
                ]);
                return false;
            }

            Mail::send('emails.order.status-update-provider', [
                'order' => $order,
                'order_id' => $order->id,
                'manufacturer_name' => $order->manufacturer->name ?? 'Unknown Manufacturer',
                'admin_name' => auth()->user()->name ?? 'MSC Admin',
                'comments' => $comments,
                'order_link' => route('provider.orders.show', $order->id),
                'status_type' => 'IVR Verification Complete',
                'status_emoji' => '✅',
            ], function ($message) use ($provider, $order) {
                $message->to($provider->email, $provider->name)
                        ->subject("✅ MSC: IVR Verification Complete for Order #{$order->id}");
            });

            Log::info('IVR verified notification sent to provider', [
                'order_id' => $order->id,
                'provider_email' => $provider->email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send IVR verified notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send IVR sent back notification to provider
     */
    public function sendIvrSentBackToProvider(ProductRequest $order, string $reason, ?string $comments = null): bool
    {
        try {
            $provider = $this->getOrderRequestor($order);
            
            if (!$provider || !$provider->email) {
                Log::warning('No provider found for IVR sent back notification', [
                    'order_id' => $order->id
                ]);
                return false;
            }

            Mail::send('emails.order.status-update-provider', [
                'order' => $order,
                'order_id' => $order->id,
                'provider_name' => $provider->name,
                'manufacturer_name' => $order->manufacturer->name ?? 'Unknown Manufacturer',
                'denial_reason' => $reason,
                'comments' => $comments,
                'order_link' => route('provider.orders.show', $order->id),
                'status_type' => 'IVR Sent Back',
                'status_emoji' => '❌',
            ], function ($message) use ($provider, $order) {
                $message->to($provider->email, $provider->name)
                        ->subject("❌ MSC: IVR Sent Back - #{$order->id}");
            });

            Log::info('IVR sent back notification sent to provider', [
                'order_id' => $order->id,
                'provider_email' => $provider->email,
                'reason' => $reason
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send IVR sent back notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send order form submitted notification to admin
     */
    public function sendOrderFormSubmittedToAdmin(ProductRequest $order, ?string $comments = null): bool
    {
        try {
            $admins = User::where('user_type', 'admin')->get();
            
            if ($admins->isEmpty()) {
                Log::warning('No admin users found to notify of order form submission', [
                    'order_id' => $order->id
                ]);
                return false;
            }

            $successCount = 0;

            foreach ($admins as $admin) {
                Mail::send('emails.order.new-order-admin', [
                    'order' => $order,
                    'order_id' => $order->id,
                    'provider_name' => $order->provider->name ?? 'Unknown Provider',
                    'date' => $order->created_at->format('F j, Y'),
                    'comment' => $comments,
                    'order_link' => route('admin.orders.show', $order->id),
                    'submission_type' => 'Order Form',
                ], function ($message) use ($admin, $order) {
                    $message->to($admin->email, $admin->name)
                            ->subject("📝 MSC: New Order Form Submitted by {$order->provider->name}");
                });

                $successCount++;
            }

            Log::info('Order form submission notification sent to admins', [
                'order_id' => $order->id,
                'admin_count' => $successCount
            ]);

            return $successCount > 0;

        } catch (Exception $e) {
            Log::error('Failed to send order form submission notification to admin', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send order submitted to manufacturer notification to provider
     */
    public function sendOrderSubmittedToManufacturerNotification(ProductRequest $order, ?string $comments = null): bool
    {
        try {
            $provider = $this->getOrderRequestor($order);
            
            if (!$provider || !$provider->email) {
                Log::warning('No provider found for order submitted to manufacturer notification', [
                    'order_id' => $order->id
                ]);
                return false;
            }

            Mail::send('emails.order.status-update-provider', [
                'order' => $order,
                'order_id' => $order->id,
                'manufacturer_name' => $order->manufacturer->name ?? 'Unknown Manufacturer',
                'admin_name' => auth()->user()->name ?? 'MSC Admin',
                'comments' => $comments,
                'order_link' => route('provider.orders.show', $order->id),
                'status_type' => 'Order Submitted to Manufacturer',
                'status_emoji' => '✅',
            ], function ($message) use ($provider, $order) {
                $message->to($provider->email, $provider->name)
                        ->subject("✅ MSC: Order Submitted to Manufacturer - Order #{$order->id}");
            });

            Log::info('Order submitted to manufacturer notification sent to provider', [
                'order_id' => $order->id,
                'provider_email' => $provider->email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send order submitted to manufacturer notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send manufacturer confirmation notification to provider
     */
    public function sendManufacturerConfirmationToProvider(ProductRequest $order, ?string $comments = null): bool
    {
        try {
            $provider = $this->getOrderRequestor($order);
            
            if (!$provider || !$provider->email) {
                Log::warning('No provider found for manufacturer confirmation notification', [
                    'order_id' => $order->id
                ]);
                return false;
            }

            Mail::send('emails.order.status-update-provider', [
                'order' => $order,
                'order_id' => $order->id,
                'provider_name' => $provider->name,
                'manufacturer_name' => $order->manufacturer->name ?? 'Unknown Manufacturer',
                'admin_name' => auth()->user()->name ?? 'MSC Admin',
                'comments' => $comments,
                'order_link' => route('provider.orders.show', $order->id),
                'status_type' => 'Order Confirmed by Manufacturer',
                'status_emoji' => '📦',
            ], function ($message) use ($provider, $order) {
                $message->to($provider->email, $provider->name)
                        ->subject("📦 MSC: Order Confirmed by Manufacturer - Order #{$order->id}");
            });

            Log::info('Manufacturer confirmation notification sent to provider', [
                'order_id' => $order->id,
                'provider_email' => $provider->email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send manufacturer confirmation notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send order denied notification to provider
     */
    public function sendOrderDeniedToProvider(ProductRequest $order, string $reason): bool
    {
        try {
            $provider = $this->getOrderRequestor($order);
            
            if (!$provider || !$provider->email) {
                Log::warning('No provider found for order denied notification', [
                    'order_id' => $order->id
                ]);
                return false;
            }

            Mail::send('emails.order.status-update-provider', [
                'order' => $order,
                'order_id' => $order->id,
                'denial_reason' => $reason,
                'order_link' => route('provider.orders.show', $order->id),
                'status_type' => 'Order Denied',
                'status_emoji' => '❌',
            ], function ($message) use ($provider, $order) {
                $message->to($provider->email, $provider->name)
                        ->subject("❌ MSC Order Denied - #{$order->id}");
            });

            Log::info('Order denied notification sent to provider', [
                'order_id' => $order->id,
                'provider_email' => $provider->email,
                'reason' => $reason
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send order denied notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send help request notification to admin
     */
    public function sendHelpRequest(\App\Models\Provider $provider, string $comment): bool
    {
        try {
            $admins = User::where('user_type', 'admin')->get();
            
            if ($admins->isEmpty()) {
                Log::warning('No admin users found to notify of help request', [
                    'provider_id' => $provider->id
                ]);
                return false;
            }

            $successCount = 0;

            foreach ($admins as $admin) {
                Mail::send('emails.admin.help-request', [
                    'provider' => $provider,
                    'provider_name' => $provider->name,
                    'provider_email' => $provider->email,
                    'comment' => $comment,
                ], function ($message) use ($admin, $provider) {
                    $message->to($admin->email, $admin->name)
                            ->subject("MSC Support Requested by {$provider->name}")
                            ->replyTo($provider->email, $provider->name);
                });

                $successCount++;
            }

            Log::info('Help request notification sent to admins', [
                'provider_id' => $provider->id,
                'admin_count' => $successCount
            ]);

            return $successCount > 0;

        } catch (Exception $e) {
            Log::error('Failed to send help request notification', [
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send password reset notification
     */
    public function sendPasswordResetNotification(User $user, string $resetToken): bool
    {
        try {
            $resetLink = url("/password/reset/{$resetToken}?email=" . urlencode($user->email));
            
            Mail::send('emails.password-reset', [
                'user' => $user,
                'resetLink' => $resetLink,
                'trackingPixel' => $this->generateTrackingPixel($user->id, 'password-reset'),
            ], function ($message) use ($user) {
                $message->to($user->email)
                        ->subject("🔐 MSC Wound Care - Password Reset Request")
                        ->priority(1)
                        ->getHeaders()
                        ->addTextHeader('X-Mailgun-Tag', 'password-reset')
                        ->addTextHeader('X-Mailgun-Variables', json_encode([
                            'user_id' => $user->id,
                            'type' => 'password-reset',
                        ]));
            });
            
            $this->logEmail($user->id, 'password-reset', $user->email);
            
            Log::info('Password reset email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            
            return true;

        } catch (Exception $e) {
            Log::error('Failed to send password reset notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send password reset confirmation
     */
    public function sendPasswordResetConfirmation(User $user): bool
    {
        try {
            Mail::send('emails.password-reset-confirmation', [
                'user' => $user,
                'trackingPixel' => $this->generateTrackingPixel($user->id, 'password-reset-confirmation'),
            ], function ($message) use ($user) {
                $message->to($user->email)
                        ->subject("✅ MSC Wound Care - Password Successfully Reset")
                        ->priority(1)
                        ->getHeaders()
                        ->addTextHeader('X-Mailgun-Tag', 'password-reset-confirmation')
                        ->addTextHeader('X-Mailgun-Variables', json_encode([
                            'user_id' => $user->id,
                            'type' => 'password-reset-confirmation',
                        ]));
            });
            
            $this->logEmail($user->id, 'password-reset-confirmation', $user->email);
            
            Log::info('Password reset confirmation email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            
            return true;

        } catch (Exception $e) {
            Log::error('Failed to send password reset confirmation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate tracking pixel for email tracking
     */
    private function generateTrackingPixel(int $userId, string $type): string
    {
        return route('email.track', [
            'user' => $userId,
            'type' => $type,
            'timestamp' => time()
        ]);
    }

    /**
     * Log email sending for tracking
     */
    private function logEmail(int $userId, string $type, string $email): void
    {
        Log::info('Email logged', [
            'user_id' => $userId,
            'type' => $type,
            'email' => $email,
            'timestamp' => now()
        ]);
    }
}

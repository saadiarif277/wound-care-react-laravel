<?php

namespace App\Services\QuickRequest\Handlers;

use App\Models\PatientManufacturerIVREpisode as Episode;
use App\Mail\ManufacturerApprovalMail;
use App\Mail\EpisodeCompletionMail;
use App\Mail\OrderStatusUpdateMail;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EpisodeCreatedNotification;
use App\Notifications\OrderApprovedNotification;

class NotificationHandler
{
    public function __construct(
        private PhiSafeLogger $logger
    ) {}

    /**
     * Notify manufacturer of episode requiring approval
     */
    public function notifyManufacturerApproval(Episode $episode): void
    {
        $this->logger->info('Sending manufacturer approval notification', [
            'episode_id' => $episode->id,
            'manufacturer_id' => $episode->manufacturer_id
        ]);

        try {
            // Get manufacturer contact
            $manufacturer = $episode->manufacturer;
            $contacts = $this->getManufacturerContacts($manufacturer);

            foreach ($contacts as $contact) {
                Mail::to($contact['email'])
                    ->queue(new ManufacturerApprovalMail($episode, $contact));
            }

            // Update episode metadata
            $episode->update([
                'metadata' => array_merge($episode->metadata ?? [], [
                    'manufacturer_notified_at' => now()->toIso8601String(),
                    'notification_sent_to' => array_column($contacts, 'email')
                ])
            ]);

            $this->logger->info('Manufacturer approval notifications sent', [
                'episode_id' => $episode->id,
                'recipients' => count($contacts)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send manufacturer approval notification', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            // Don't throw - notification failure shouldn't break the workflow
        }
    }

    /**
     * Notify relevant parties of episode completion
     */
    public function notifyEpisodeCompletion(Episode $episode): void
    {
        $this->logger->info('Sending episode completion notifications', [
            'episode_id' => $episode->id
        ]);

        try {
            // Notify provider
            if ($provider = $this->getProviderUser($episode)) {
                $provider->notify(new OrderApprovedNotification($episode));
            }

            // Notify office manager
            if ($officeManager = $this->getOfficeManagerUser($episode)) {
                Mail::to($officeManager->email)
                    ->queue(new EpisodeCompletionMail($episode));
            }

            // Update notification metadata
            $episode->update([
                'metadata' => array_merge($episode->metadata ?? [], [
                    'completion_notified_at' => now()->toIso8601String()
                ])
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send episode completion notification', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send order status update notification
     */
    public function notifyOrderStatusUpdate($order, string $oldStatus, string $newStatus): void
    {
        $this->logger->info('Sending order status update notification', [
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);

        try {
            $episode = $order->episode;

            // Determine who to notify based on status change
            $recipients = $this->determineStatusUpdateRecipients($order, $oldStatus, $newStatus);

            foreach ($recipients as $recipient) {
                Mail::to($recipient['email'])
                    ->queue(new OrderStatusUpdateMail($order, $oldStatus, $newStatus));
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to send order status update notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send episode created notification
     */
    public function notifyEpisodeCreated(Episode $episode): void
    {
        try {
            // Notify admins of new episode
            $admins = $this->getAdminUsers();

            Notification::send($admins, new EpisodeCreatedNotification($episode));

            $this->logger->info('Episode created notifications sent', [
                'episode_id' => $episode->id,
                'admin_count' => count($admins)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send episode created notification', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get manufacturer contacts
     */
    private function getManufacturerContacts($manufacturer): array
    {
        // Primary contact
        $contacts = [];

        if ($manufacturer->primary_contact_email) {
            $contacts[] = [
                'name' => $manufacturer->primary_contact_name ?? 'Primary Contact',
                'email' => $manufacturer->primary_contact_email,
                'type' => 'primary'
            ];
        }

        // Approval team contacts
        if ($manufacturer->approval_team_emails) {
            $emails = is_array($manufacturer->approval_team_emails)
                ? $manufacturer->approval_team_emails
                : json_decode($manufacturer->approval_team_emails, true);

            foreach ($emails as $email) {
                $contacts[] = [
                    'name' => 'Approval Team',
                    'email' => $email,
                    'type' => 'approval_team'
                ];
            }
        }

        // Fallback to general email
        if (empty($contacts) && $manufacturer->general_email) {
            $contacts[] = [
                'name' => $manufacturer->name,
                'email' => $manufacturer->general_email,
                'type' => 'general'
            ];
        }

        return $contacts;
    }

    /**
     * Get provider user from episode
     */
    private function getProviderUser(Episode $episode)
    {
        // This would lookup the user based on practitioner FHIR ID
        return \App\Models\User::where('provider_fhir_id', $episode->practitioner_fhir_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get office manager user from episode
     */
    private function getOfficeManagerUser(Episode $episode)
    {
        // This would lookup based on organization
        return \App\Models\User::where('organization_fhir_id', $episode->organization_fhir_id)
            ->where('role', 'office_manager')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get admin users
     */
    private function getAdminUsers()
    {
        return \App\Models\User::role(['msc-admin', 'msc-manager'])
            ->where('is_active', true)
            ->where('receive_notifications', true)
            ->get();
    }

    /**
     * Determine recipients for status updates
     */
    private function determineStatusUpdateRecipients($order, string $oldStatus, string $newStatus): array
    {
        $recipients = [];
        $episode = $order->episode;

        // Provider gets notified of approvals and rejections
        if (in_array($newStatus, ['approved', 'rejected'])) {
            if ($provider = $this->getProviderUser($episode)) {
                $recipients[] = [
                    'email' => $provider->email,
                    'name' => $provider->name
                ];
            }
        }

        // Office manager gets notified of shipments
        if (in_array($newStatus, ['shipped', 'delivered'])) {
            if ($officeManager = $this->getOfficeManagerUser($episode)) {
                $recipients[] = [
                    'email' => $officeManager->email,
                    'name' => $officeManager->name
                ];
            }
        }

        // Manufacturer gets notified of cancellations
        if ($newStatus === 'cancelled' && $oldStatus !== 'pending') {
            $contacts = $this->getManufacturerContacts($episode->manufacturer);
            $recipients = array_merge($recipients, $contacts);
        }

        return $recipients;
    }

    /**
     * Send SMS notification (optional)
     */
    public function sendSmsNotification(string $phone, string $message): void
    {
        // This would integrate with SMS service (Twilio, etc.)
        $this->logger->info('SMS notification would be sent', [
            'phone' => substr($phone, 0, 3) . '****' . substr($phone, -2),
            'message_length' => strlen($message)
        ]);
    }
}

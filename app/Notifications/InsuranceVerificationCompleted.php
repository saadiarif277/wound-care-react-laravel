<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Episode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InsuranceVerificationCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private Episode $episode,
        private array $verificationSummary
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject('Insurance Verification Complete - Episode ' . $this->episode->id)
            ->greeting('Insurance Verification Complete')
            ->line('Insurance verification has been completed for episode ' . $this->episode->id)
            ->line('Patient: ' . $this->episode->patient_display);

        // Add verification details
        foreach ($this->verificationSummary as $type => $details) {
            $mail->line('');
            $mail->line(ucfirst($type) . ' Insurance:');
            $mail->line('- Status: ' . ucfirst($details['status']));
            $mail->line('- Eligibility: ' . ($details['eligibility'] ?? 'Unknown'));
            
            if (isset($details['copay']) && $details['copay'] !== null) {
                $mail->line('- Copay: $' . number_format($details['copay'], 2));
            }
            
            if (isset($details['deductible_remaining']) && $details['deductible_remaining'] !== null) {
                $mail->line('- Deductible Remaining: $' . number_format($details['deductible_remaining'], 2));
            }
        }

        return $mail
            ->action('View Episode', url('/episodes/' . $this->episode->id))
            ->line('Thank you for using MSC Wound Portal.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'episode_id' => $this->episode->id,
            'patient_display' => $this->episode->patient_display,
            'verification_summary' => $this->verificationSummary,
            'completed_at' => now()->toIso8601String(),
        ];
    }
}
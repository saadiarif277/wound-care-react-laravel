<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Episode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EpisodeCreationFailedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private Episode $episode,
        private string $errorMessage
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
        return (new MailMessage())
            ->subject('Episode Creation Failed')
            ->greeting('Episode Creation Failed')
            ->line('We encountered an error while creating your wound care episode.')
            ->line('Episode ID: ' . $this->episode->id)
            ->line('Error: ' . $this->errorMessage)
            ->line('Please try again or contact support if the issue persists.')
            ->action('View Dashboard', url('/dashboard'))
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
            'error' => $this->errorMessage,
            'failed_at' => now()->toIso8601String(),
        ];
    }
}
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Episode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EpisodeCreatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private Episode $episode
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
            ->subject('New Wound Care Episode Created')
            ->greeting('Episode Created Successfully')
            ->line('Your wound care episode has been created successfully.')
            ->line('Episode ID: ' . $this->episode->id)
            ->line('Patient: ' . $this->episode->patient_display)
            ->line('Status: ' . ucfirst($this->episode->status))
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
            'status' => $this->episode->status,
            'created_at' => $this->episode->created_at->toIso8601String(),
        ];
    }
}
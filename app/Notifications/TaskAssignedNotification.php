<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private Task $task
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
            ->subject('New Task Assigned: ' . $this->task->title)
            ->greeting('New Task Assigned')
            ->line('You have been assigned a new task.')
            ->line('Task: ' . $this->task->title)
            ->line('Type: ' . ucfirst($this->task->type))
            ->line('Priority: ' . ucfirst($this->task->priority ?? 'normal'));

        if ($this->task->description) {
            $mail->line('Description: ' . $this->task->description);
        }

        if ($this->task->due_date) {
            $mail->line('Due Date: ' . $this->task->due_date->format('M d, Y h:i A'));
        }

        return $mail
            ->action('View Task', url('/tasks/' . $this->task->id))
            ->line('Please complete this task as soon as possible.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'type' => $this->task->type,
            'priority' => $this->task->priority,
            'due_date' => $this->task->due_date?->toIso8601String(),
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
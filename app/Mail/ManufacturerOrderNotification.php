<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Episode;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManufacturerOrderNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Episode $episode,
        public Order $order,
        public array $orderDetails,
        public string $notificationType,
        public array $attachments = []
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->notificationType) {
            'new_order' => 'New Order - ' . $this->episode->patient_display,
            'order_approved' => 'Order Approved - ' . $this->episode->patient_display,
            'urgent_order' => 'URGENT: New Order - ' . $this->episode->patient_display,
            'order_modification' => 'Order Modified - ' . $this->episode->patient_display,
            'order_cancellation' => 'Order Cancelled - ' . $this->episode->patient_display,
            default => 'Order Notification - ' . $this->episode->patient_display,
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.manufacturer.order-notification',
            with: [
                'episode' => $this->episode,
                'order' => $this->order,
                'orderDetails' => $this->orderDetails,
                'notificationType' => $this->notificationType,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $mailAttachments = [];

        foreach ($this->attachments as $attachment) {
            if (file_exists($attachment['path'])) {
                $mailAttachments[] = Attachment::fromPath($attachment['path'])
                    ->as($attachment['name'])
                    ->withMime($attachment['mime']);
            }
        }

        return $mailAttachments;
    }
}
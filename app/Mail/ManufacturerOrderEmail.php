<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class ManufacturerOrderEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $orderDetails,
        public array $attachments = []
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Order Request - {$this->orderDetails['order_number']} - {$this->orderDetails['patient']['display_id']}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.manufacturer-order',
            with: [
                'order' => $this->orderDetails,
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
            if (is_string($attachment) && file_exists($attachment)) {
                $mailAttachments[] = Attachment::fromPath($attachment);
            } elseif (is_array($attachment) && isset($attachment['path'])) {
                $attach = Attachment::fromPath($attachment['path']);
                if (isset($attachment['name'])) {
                    $attach->as($attachment['name']);
                }
                if (isset($attachment['mime'])) {
                    $attach->withMime($attachment['mime']);
                }
                $mailAttachments[] = $attach;
            }
        }
        
        return $mailAttachments;
    }
}
<?php

namespace App\Mail;

use App\Models\ProductRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderNotification extends Mailable
{
    use Queueable, SerializesModels;

    public ProductRequest $order;
    public string $status;
    public ?string $message;

    /**
     * Create a new message instance.
     */
    public function __construct(ProductRequest $order, string $status, ?string $message = null)
    {
        $this->order = $order;
        $this->status = $status;
        $this->message = $message;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->status) {
            'send_back' => "MSC Wound Care - Order #{$this->order->request_number} Requires Attention",
            'denied' => "MSC Wound Care - Order #{$this->order->request_number} Denied",
            'approved' => "MSC Wound Care - Order #{$this->order->request_number} Approved",
            default => "MSC Wound Care - Order #{$this->order->request_number} Status Update"
        };

        return new Envelope(
            subject: $subject,
            from: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.provider-notification',
            with: [
                'order' => $this->order,
                'status' => $this->status,
                'message' => $this->message,
                'provider' => $this->order->provider,
                'facility' => $this->order->facility,
            ],
        );
    }
}

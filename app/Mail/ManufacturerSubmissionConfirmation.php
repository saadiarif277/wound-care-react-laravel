<?php

namespace App\Mail;

use App\Models\ProductRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManufacturerSubmissionConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public ProductRequest $order;

    /**
     * Create a new message instance.
     */
    public function __construct(ProductRequest $order)
    {
        $this->order = $order;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "MSC Wound Care - Order #{$this->order->request_number} Submitted",
            from: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.manufacturer-submission-confirmation',
            with: [
                'order' => $this->order,
                'provider' => $this->order->provider,
                'facility' => $this->order->facility,
                'products' => $this->order->order_items ?? [],
            ],
        );
    }
}

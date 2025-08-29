<?php

namespace App\Mail;

use App\Models\ProductRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class IvrSubmissionToManufacturer extends Mailable
{
    use Queueable, SerializesModels;

    public ProductRequest $order;
    public string $ivrPdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct(ProductRequest $order, string $ivrPdfPath)
    {
        $this->order = $order;
        $this->ivrPdfPath = $ivrPdfPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "MSC Wound Care - Insurance Verification Request #{$this->order->request_number}",
            from: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ivr-submission-to-manufacturer',
            with: [
                'order' => $this->order,
                'provider' => $this->order->provider,
                'facility' => $this->order->facility,
                'patient' => $this->order->patient_display_id,
                'products' => $this->order->order_items ?? [],
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorage($this->ivrPdfPath)
                ->as("IVR-{$this->order->request_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
